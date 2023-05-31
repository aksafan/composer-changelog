<?php

declare(strict_types=1);

namespace aksafan\composer\changelog;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Version\VersionParser;
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
    private const DELIMITER = '/';

    private const UPGRADE_FILE_NAME = 'UPGRADE.md';

    private const NOTES_LIMIT = 250;

    private const NOTES_LIMIT_ERROR_MESSAGE = '  <fg=yellow;options=bold>The relevant notes for your upgrade are too long to be displayed here.</>';

    /**
     * @var $packageUpdates array Noted package updates
     */
    private array $packageUpdates = [];

    /**
     * @var $vendorDir string Path to the vendor directory
     */
    private string $vendorDir;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->vendorDir = rtrim($composer->getConfig()->get('vendor-dir'), self::DELIMITER);
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
     * @return void
     */
    public function checkPackageUpdates(PackageEvent $event): void
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
                'direction' => VersionParser::isUpgrade(
                    $operation->getInitialPackage()->getVersion(),
                    $operation->getTargetPackage()->getVersion()
                ) ? 'up' : 'down',
            ];
        }
    }

    /**
     * Listens to ScriptEvents::POST_UPDATE_CMD event to display information about upgrade notes if appropriate.
     *
     * @param Script\Event $event
     * @return void
     */
    public function showUpgradeNotes(Script\Event $event): void
    {
        $io = $event->getIO();
        foreach ($this->packageUpdates as $packageName => $packageInfo) {
            // Do not show a notice on up/downgrades between dev versions. Avoid messages like from version dev-master to dev-master.
            if ((string) $packageInfo['fromPretty'] === (string) $packageInfo['toPretty']) {
                continue;
            }

            // Print the relevant upgrade notes for the upgrade:
            // - only on upgrade, not on downgrade;
            // - only if the "from" version is non-dev, otherwise we have no idea which notes to show.
            $isNumericVersion = $this->isNumericVersion((string) $packageInfo['fromPretty']);
            if ((string) $packageInfo['direction'] === 'up' && $isNumericVersion) {
                $notes = $this->findUpgradeNotes((string) $packageName, (string) $packageInfo['fromPretty']);
                if (!$notes) {
                    // No relevant upgrade notes, do not show anything skipping.
                    continue;
                }

                $this->printUpgradeIntro($io, $packageInfo);
                $this->checkNotesLimit($notes, $io);
                $io->write(PHP_EOL . '  You can find the upgrade notes for all versions online at: ', false);
            } else {
                $this->printUpgradeIntro($io, $packageInfo);
                $io->write(PHP_EOL . '  You can find the upgrade notes online at: ', false);
            }
            $io->write($packageInfo['sourceUrl']);
        }
    }

    /**
     * Was added to support composer-plugin-api v2+
     *
     * Remove any hooks from Composer
     *
     * This will be called when a plugin is deactivated before being
     * uninstalled, but also before it gets upgraded to a new version
     * so the old one can be deactivated and the new one activated.
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Was added to support composer-plugin-api v2+
     *
     * Prepare the plugin to be uninstalled
     *
     * This will be called after deactivate.
     *
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Prints upgrade intro
     *
     * @param IOInterface $io
     * @param array $package
     * @return void
     */
    private function printUpgradeIntro(IOInterface $io, array $package): void
    {
        $io->write(PHP_EOL . '  <fg=yellow;options=bold>Seems you have '
            . ((string) $package['direction'] === 'up' ? 'upgraded ' : 'downgraded ')
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
        $upgradeFile = $this->vendorDir . self::DELIMITER . $packageName . self::DELIMITER . self::UPGRADE_FILE_NAME;
        if (!is_file($upgradeFile) || !is_readable($upgradeFile)) {
            return null;
        }
        $lines = preg_split('~\R~', file_get_contents($upgradeFile));
        if ($lines) {
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
                if (
                    version_compare($matches[2], $fromVersion, '<')
                    && ($foundExactMatch || version_compare($matches[2], $fromVersionMajor, '<'))
                ) {
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

    /**
     * Makes safety check: do not display notes if they are too many
     *
     * @param array $notes
     * @param IOInterface $io
     * @return void
     */
    private function checkNotesLimit(array $notes, IOInterface $io): void
    {
        if (count($notes) > self::NOTES_LIMIT) {
            $io->write(PHP_EOL . self::NOTES_LIMIT_ERROR_MESSAGE);
        } else {
            $io->write(PHP_EOL . '  ' . trim(implode(PHP_EOL . ' ', $notes)));
        }
    }
}
