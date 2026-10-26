<?php

namespace Mdrisss\BookstackFiles;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use BookStack\View\ViewBlockManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Point d'entrée du package, découvert automatiquement par Laravel.
 *
 * Le package ne s'accroche à BookStack que par ses points d'extension publics
 * (événements Theme::listen et blocs de vue) : aucun fichier de BookStack n'est modifié.
 */
class BookstackFilesServiceProvider extends ServiceProvider
{
    public const VERSION = '2.0.0';

    public function boot(): void
    {
        $basePath = dirname(__DIR__);

        $this->loadMigrationsFrom($basePath . '/database/migrations');
        $this->loadViewsFrom($basePath . '/resources/views', 'bookstack-files');
        $this->loadTranslationsFrom($basePath . '/lang', 'bookstack-files');

        Theme::listen(ThemeEvents::ROUTES_REGISTER_WEB, function (Router $router) {
            // Ressources JS/CSS, servies avec un type MIME explicite
            $router->get('/bookstack-files/assets/{asset}', [EntityFileController::class, 'asset'])
                ->where('asset', 'bookstack-files\.(js|css)');
        });

        Theme::listen(ThemeEvents::ROUTES_REGISTER_WEB_AUTH, function (Router $router) {
            $types = implode('|', EntityFileService::SUPPORTED_TYPES);

            $router->get('/bookstack-files/entities/{type}/{id}/files', [EntityFileController::class, 'index'])
                ->where(['type' => $types, 'id' => '[0-9]+']);
            $router->post('/bookstack-files/entities/{type}/{id}/files', [EntityFileController::class, 'store'])
                ->where(['type' => $types, 'id' => '[0-9]+']);
            $router->get('/bookstack-files/files/{fileId}', [EntityFileController::class, 'download'])
                ->where('fileId', '[0-9]+');
            // Le nom de fichier final est cosmétique : il sert de titre au visualiseur PDF du navigateur
            $router->get('/bookstack-files/files/{fileId}/view/{fileName?}', [EntityFileController::class, 'view'])
                ->where(['fileId' => '[0-9]+', 'fileName' => '.*']);
            $router->delete('/bookstack-files/files/{fileId}', [EntityFileController::class, 'destroy'])
                ->where('fileId', '[0-9]+');
        });

        Theme::listen(ThemeEvents::VIEW_BLOCKS_REGISTER, function (ViewBlockManager $manager) {
            $manager->register('shelves-show', 'left', EntityFilesBlock::class);
            $manager->register('books-show', 'left', EntityFilesBlock::class);
            $manager->register('chapters-show', 'left', EntityFilesBlock::class);
            $manager->register('pages-show', 'left', EntityFilesBlock::class);
        });

        // Supprime les fichiers liés quand une entité est définitivement détruite (vidage de la corbeille)
        foreach ([Page::class, Chapter::class, Book::class, Bookshelf::class] as $entityClass) {
            $entityClass::forceDeleted(function (Entity $entity) {
                $this->app->make(EntityFileService::class)->deleteAllForEntity($entity);
            });
        }

        $this->app->booted(fn () => $this->registerActivityTranslations($basePath . '/lang'));
    }

    /**
     * Ajoute les libellés de nos événements au groupe de traduction « activities » de BookStack,
     * utilisé par le journal d'audit et les listes d'activité.
     */
    protected function registerActivityTranslations(string $langPath): void
    {
        $translator = $this->app->make('translator');

        foreach (glob($langPath . '/*/activities.php') as $file) {
            $locale = basename(dirname($file));
            $lines = [];
            foreach (require $file as $key => $text) {
                $lines['activities.' . $key] = $text;
            }

            // Charger d'abord le groupe d'origine, sinon addLines() empêcherait son chargement
            $translator->load('*', 'activities', $locale);
            $translator->addLines($lines, $locale);
        }
    }
}
