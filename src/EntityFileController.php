<?php

namespace Mdrisss\BookstackFiles;

use BookStack\Entities\Models\Entity;
use BookStack\Exceptions\FileUploadException;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityFileController extends Controller
{
    public const ACTIVITY_UPLOAD = 'bookstack_files_upload';
    public const ACTIVITY_DELETE = 'bookstack_files_delete';

    public function __construct(
        protected EntityFileService $files,
    ) {
    }

    /**
     * Liste les fichiers liés à une entité.
     */
    public function index(string $type, int $id): JsonResponse
    {
        $entity = $this->files->findVisibleEntityOrFail($type, $id);
        $canEdit = userCan(Permission::Update, $entity);

        return response()->json([
            'can_upload' => $this->canUploadTo($entity),
            'files'      => $this->files->listForEntity($entity)
                ->map(fn (EntityFile $file) => $this->formatFile($file, $canEdit))
                ->values(),
        ]);
    }

    /**
     * Téléverse un nouveau fichier lié à une entité.
     */
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $entity = $this->files->findVisibleEntityOrFail($type, $id);
        if (!$this->canUploadTo($entity)) {
            $this->showPermissionError();
        }

        $this->validate($request, $this->files->getValidationRules());

        try {
            $file = $this->files->saveUpload($request->file('file'), $entity);
        } catch (FileUploadException $e) {
            return $this->jsonError($e->getMessage());
        }

        $this->logActivity(self::ACTIVITY_UPLOAD, $file);

        return response()->json($this->formatFile($file, true), 201);
    }

    /**
     * Télécharge le fichier.
     */
    public function download(int $fileId): StreamedResponse
    {
        [$file] = $this->files->findVisibleFileOrFail($fileId);

        return $this->createDownload()->streamedDirectly(
            $this->files->readStream($file),
            $file->getFileName(),
            $this->files->getStoredSize($file),
        );
    }

    /**
     * Sert le fichier en ligne (aperçu dans le popup).
     * Le type MIME est filtré par BookStack : un type non sûr est servi en téléchargement.
     */
    public function view(int $fileId): StreamedResponse
    {
        [$file] = $this->files->findVisibleFileOrFail($fileId);

        return $this->createDownload()->streamedInline(
            $this->files->readStream($file),
            $file->getFileName(),
            $this->files->getStoredSize($file),
        );
    }

    /**
     * Supprime un fichier lié.
     */
    public function destroy(int $fileId): JsonResponse
    {
        [$file, $entity] = $this->files->findVisibleFileOrFail($fileId);
        if (!$this->canDelete($file, $entity)) {
            $this->showPermissionError();
        }

        $this->files->delete($file);
        $this->logActivity(self::ACTIVITY_DELETE, $file);

        return response()->json(['message' => trans('bookstack-files::messages.deleted')]);
    }

    /**
     * Sert les ressources front du module (hors authentification, contenu statique).
     */
    public function asset(string $asset): BinaryFileResponse
    {
        $types = ['js' => 'text/javascript', 'css' => 'text/css'];
        $extension = pathinfo($asset, PATHINFO_EXTENSION);
        $path = dirname(__DIR__) . '/resources/assets/' . basename($asset);
        if (!isset($types[$extension]) || !file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type'  => $types[$extension] . '; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Ajout : droit de modifier l'entité + droit de créer des pièces jointes.
     */
    protected function canUploadTo(Entity $entity): bool
    {
        return userCan(Permission::AttachmentCreateAll) && userCan(Permission::Update, $entity);
    }

    /**
     * Suppression : droit de modifier l'entité + droit de supprimer ce fichier (tous / les siens).
     */
    protected function canDelete(EntityFile $file, Entity $entity): bool
    {
        return userCan(Permission::Update, $entity) && userCan(Permission::AttachmentDelete, $file);
    }

    protected function formatFile(EntityFile $file, bool $canEditEntity): array
    {
        return [
            'id'           => $file->id,
            'name'         => $file->name,
            'extension'    => strtolower($file->extension),
            'size'         => $file->size,
            'previewable'  => $file->isPreviewable(),
            'created_at'   => $file->created_at?->toIso8601String(),
            'created_by'   => $file->createdBy?->name,
            'download_url' => $file->getUrl(),
            'view_url'     => $file->getUrl() . '/view/' . rawurlencode($file->getFileName()),
            'can_delete'   => $canEditEntity && userCan(Permission::AttachmentDelete, $file),
        ];
    }
}
