<?php

declare(strict_types=1);

namespace aksafan\composer\changelog;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script;
use Composer\Script\ScriptEvents;
use function file_get_contents;
use function is_file;
use function is_readable;
use function preg_match;
use function preg_split;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var $packageUpdates array Noted package updates */
    private $packageUpdates = [];

    /** @var $vendorDir string Path to the vendor directory */
    private $vendorDir;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->vendorDir = rtrim($composer->getConfig()->get('vendor-dir'), '/');
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.

     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_UPDATE => 'checkPackageUpdates',
            ScriptEvents::POST_UPDATE_CMD => 'showUpgradeNotes',
        ];
    }

    /**
     * Listens to PackageEvents::POST_PACKAGE_UPDATE event and takes note of the package updates.
     *
     * @param PackageEvent $event
     */
    public function checkPackageUpdates(PackageEvent $event)
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $this->packageUpdates[$operation->getInitialPackage()->getName()] = [
                'namePretty' => $operation->getInitialPackage()->getPrettyName(),
                'sourceUrl' => $operation->getInitialPackage()->getSourceUrl(),
                'from' => $operation->getInitialPackage()->getVersion(),
                'fromPretty' => $operation->getInitialPackage()->getPrettyVersion(),
                'to' => $operation->getTargetPackage()->getVersion(),
                'toPretty' => $operation->getTargetPackage()->getPrettyVersion(),
                'direction' => $event->getPolicy()->versionCompare(
                    $operation->getInitialPackage(),
                    $operation->getTargetPackage(),
                    '<'
                ) ? 'up' : 'down',
            ];
        }
    }

    /**
     * Listens to ScriptEvents::POST_UPDATE_CMD event to display information about upgrade notes if appropriate.
     *
     * @param Script\Event $event
     */
    public function showUpgradeNotes(Script\Event $event)
    {
        foreach ($this->packageUpdates as $packageName => $packageInfo) {
            // Do not show a notice on up/downgrades between dev versions. Avoid messages like from version dev-master to dev-master.
            if ((string) $packageInfo['fromPretty'] === (string) $packageInfo['toPretty']) {
                continue;
            }

            $io = $event->getIO();
            // Print the relevant upgrade notes for the upgrade:
            // - only on upgrade, not on downgrade;
            // - only if the "from" version is non-dev, otherwise we have no idea which notes to show.
            if ((string) $packageInfo['direction'] === 'up' && $this->isNumericVersion((string) $packageInfo['fromPretty'])) {
                $notes = $this->findUpgradeNotes((string) $packageName, (string) $packageInfo['fromPretty']);
                if (null === $notes) {
                    // No relevant upgrade notes, do not show anything skipping.
                    continue;
                }

                $this->printUpgradeIntro($io, $packageInfo);
                if ($notes) {
                    // safety check: do not display notes if they are too many
                    if (count($notes) > 250) {
                        $io->write(PHP_EOL . '  <fg=yellow;options=bold>The relevant notes for your upgrade are too long to be displayed here.</>');
                    } else {
                        $io->write(PHP_EOL . '  ' . trim(implode(PHP_EOL . ' ', $notes)));
                    }
                }
                $io->write(PHP_EOL . '  You can find the upgrade notes for all versions online at:');
            } else {
                $this->printUpgradeIntro($io, $packageInfo);
                $io->write(PHP_EOL . '  You can find the upgrade notes online at:');
            }
            $io->write($packageInfo['sourceUrl']);
        }
    }

    /**
     * Prints upgrade intro
     *
     * @param IOInterface $io
     * @param array $package
     */
    private function printUpgradeIntro(IOInterface $io, array $package): void
    {
        $io->write(PHP_EOL . '  <fg=yellow;options=bold>Seems you have '
            . ($package['direction'] === 'up' ? 'upgraded' : 'downgraded')
            . $package['namePretty'] . ' from version '
            . $package['fromPretty'] . ' to ' . $package['toPretty'] . '.</>'
        );
        $io->write(PHP_EOL . '  <options=bold>Please check the upgrade notes for possible incompatible changes and adjust your application code accordingly.</>');
    }

    /**
     * Reads upgrade notes from a files and returns an array of lines
     *
     * @param string $packageName
     * @param string $fromVersion until which version to read the notes
     *
     * @return array|null
     */
    private function findUpgradeNotes(string $packageName, string $fromVersion): ?array
    {
        $upgradeFile = $this->vendorDir . '/' . $packageName . '/UPGRADE.md';
        if (! is_file($upgradeFile) || ! is_readable($upgradeFile)) {
            return null;
        }
        $lines = preg_split('~\R~', file_get_contents($upgradeFile));
        if (! $lines) {
            return null;
        }

        return $this->collectUpgradeNotes($fromVersion, $lines, $this->getMajorVersion($fromVersion));
    }

    /**
     * Check whether a version is numeric, e.g. 2.0.10.
     * @see https://semver.org/ for Semver info.
     *
     * @param string $version
     *
     * @return bool
     */
    private function isNumericVersion(string $version): bool
    {
        return (bool) preg_match('~^([0-9]\.[0-9]+\.?[0-9\.]*)~', $version);
    }

    /**
     * @param string $fromVersion
     *
     * @return string
     */
    private function getMajorVersion(string $fromVersion): string
    {
        if (preg_match('/^([0-9]\.[0-9]+\.?[0-9]*)/', $fromVersion, $m)) {
            $fromVersionMajor = $m[1];
        } else {
            $fromVersionMajor = $fromVersion;
        }

        return (string) $fromVersionMajor;
    }

    /**
     * @param string $fromVersion
     * @param array $lines
     * @param string $fromVersionMajor
     *
     * @return array
     */
    private function collectUpgradeNotes(string $fromVersion, array $lines, string $fromVersionMajor): array
    {
        $relevantLines = [];
        $consuming = false;
        // Whether an exact match on $fromVersion has been encountered.
        $foundExactMatch = false;
        foreach ($lines as $line) {
            if (preg_match('/^Upgrade from (?P<name>\w+) ([0-9]\.[0-9]+\.?[0-9\.]*)/i', $line, $matches)) {
                if ($matches[2] === $fromVersion) {
                    $foundExactMatch = true;
                }
                if (version_compare($matches[2], $fromVersion, '<') && ($foundExactMatch || version_compare($matches[2],
                            $fromVersionMajor, '<'))) {
                    break;
                }
                $consuming = true;
            }
            if ($consuming) {
                $relevantLines[] = $line;
            }
        }

        return $relevantLines;
    }
}
