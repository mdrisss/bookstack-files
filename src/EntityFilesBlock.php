<?php

namespace Mdrisss\BookstackFiles;

use BookStack\Entities\Models\Entity;
use BookStack\View\BaseViewBlock;

/**
 * Bloc de barre latérale « Fichiers liés », affiché sur les vues
 * d'étagère, de livre, de chapitre et de page.
 * La liste est chargée de manière asynchrone par bookstack-files.js.
 */
class EntityFilesBlock extends BaseViewBlock
{
    public static function getId(): string
    {
        return 'bookstack_files';
    }

    public static function getLabel(): string
    {
        return trans('bookstack-files::messages.block_title');
    }

    public function getView(array $viewData): string
    {
        return 'bookstack-files::block';
    }

    public function getViewData(array $viewData): array
    {
        $entity = null;
        foreach (['page', 'chapter', 'book', 'shelf'] as $key) {
            if (($viewData[$key] ?? null) instanceof Entity) {
                $entity = $viewData[$key];
                break;
            }
        }

        return ['bsfEntity' => $entity];
    }
}
