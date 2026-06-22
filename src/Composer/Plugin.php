<?php

namespace ChiefTools\PhpCsFixer\Composer;

use Composer\Composer;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use Composer\Plugin\PluginInterface;
use ChiefTools\PhpCsFixer\EditorConfigInstaller;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installEditorConfig',
            ScriptEvents::POST_UPDATE_CMD  => 'installEditorConfig',
        ];
    }

    public function installEditorConfig(Event $event): void
    {
        $vendorDirectory = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot     = dirname($vendorDirectory);
        $status          = (new EditorConfigInstaller)->install($projectRoot, dirname(__DIR__, 2) . '/.editorconfig');

        match ($status) {
            EditorConfigInstaller::COPIED  => $event->getIO()->write('<info>Copied Chief Tools .editorconfig.</info>'),
            EditorConfigInstaller::UPDATED => $event->getIO()->write('<info>Updated Chief Tools .editorconfig.</info>'),
            default                        => null,
        };
    }
}
