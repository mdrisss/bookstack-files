<?php

namespace Afridocs\Files;

use BookStack\Entities\Models\Entity;
use BookStack\View\BaseViewBlock;

/**
 * Bloc de barre latérale « Fichiers liés », affiché sur les vues
 * d'étagère, de livre, de chapitre et de page.
 * La liste est chargée de manière asynchrone par afridocs-files.js.
 */
class EntityFilesBlock extends BaseViewBlock
{
    public static function getId(): string
    {
        return 'afridocs_entity_files';
    }

    public static function getLabel(): string
    {
        return trans('afridocs-files::messages.block_title');
    }

    public function getView(array $viewData): string
    {
        return 'afridocs-files::block';
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

        return ['afridocsEntity' => $entity];
    }
}
