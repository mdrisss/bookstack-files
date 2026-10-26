<?php

namespace Afridocs\Files;

use BookStack\Activity\Models\Loggable;
use BookStack\App\Model;
use BookStack\Users\Models\HasCreatorAndUpdater;
use BookStack\Users\Models\OwnableInterface;

/**
 * Fichier associé à une entité BookStack (étagère, livre, chapitre ou page).
 *
 * @property int    $id
 * @property string $entity_type
 * @property int    $entity_id
 * @property string $name
 * @property string $path
 * @property string $extension
 * @property int    $size
 * @property int    $order
 */
class EntityFile extends Model implements OwnableInterface, Loggable
{
    use HasCreatorAndUpdater;

    /**
     * Extensions affichées directement dans le popup d'aperçu.
     * Le type MIME réellement servi reste filtré par le WebSafeMimeSniffer de BookStack.
     */
    public const PREVIEWABLE_EXTENSIONS = [
        'pdf', 'txt', 'md', 'csv', 'log', 'json',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp',
        'mp4', 'webm', 'mp3', 'ogg', 'wav',
    ];

    protected $table = 'afridocs_entity_files';
    protected $hidden = ['path'];
    protected $casts = [
        'entity_id' => 'integer',
        'size'      => 'integer',
        'order'     => 'integer',
    ];

    public function isPreviewable(): bool
    {
        return in_array(strtolower($this->extension), self::PREVIEWABLE_EXTENSIONS);
    }

    public function getFileName(): string
    {
        if ($this->extension && !str_ends_with(strtolower($this->name), '.' . strtolower($this->extension))) {
            return $this->name . '.' . $this->extension;
        }

        return $this->name;
    }

    public function getUrl(): string
    {
        return url('/afridocs/files/' . $this->id);
    }

    public function logDescriptor(): string
    {
        return "({$this->id}) {$this->name} [{$this->entity_type}:{$this->entity_id}]";
    }
}
