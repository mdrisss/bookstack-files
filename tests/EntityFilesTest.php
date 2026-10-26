<?php

namespace Afridocs\Files\Tests;

use Afridocs\Files\EntityFile;
use BookStack\Activity\Models\Activity;
use BookStack\Entities\Models\Entity;
use Tests\TestCase;

/**
 * Lancement depuis la racine de BookStack : vendor/bin/phpunit vendor/mdrisss/bookstack-files/tests
 */
class EntityFilesTest extends TestCase
{
    /**
     * Exécute le test sans thème actif : le package doit fonctionner seul.
     */
    protected function withModule(callable $callback): void
    {
        $this->runWithEnv(['APP_THEME' => null, 'APP_DEFAULT_LOCALE' => 'en'], function () use ($callback) {
            // Le rôle Editor de BookStack n'a pas les permissions de pièces jointes par défaut
            $this->permissions->grantUserRolePermissions($this->users->editor(), [
                'attachment-create-all', 'attachment-delete-all',
            ]);
            $callback();
        });
    }

    protected function filesUrl(Entity $entity): string
    {
        return '/afridocs/entities/' . $entity->getMorphClass() . '/' . $entity->id . '/files';
    }

    protected function upload(Entity $entity, string $name = 'procedure.txt')
    {
        return $this->withHeader('Accept', 'application/json')
            ->post($this->filesUrl($entity), ['file' => $this->files->uploadedTextFile($name)]);
    }

    protected function tearDown(): void
    {
        foreach (EntityFile::query()->get() as $file) {
            @unlink(storage_path($file->path));
        }
        parent::tearDown();
    }

    public function test_editor_can_upload_to_each_entity_type_and_activity_is_logged()
    {
        $this->withModule(function () {
            $this->asEditor();
            $entities = [$this->entities->shelf(), $this->entities->book(), $this->entities->chapter(), $this->entities->page()];

            foreach ($entities as $entity) {
                $resp = $this->upload($entity, 'norme-' . $entity->getMorphClass() . '.txt');
                $resp->assertStatus(201);
                $resp->assertJson(['name' => 'norme-' . $entity->getMorphClass() . '.txt', 'can_delete' => true]);

                $this->assertDatabaseHas('afridocs_entity_files', [
                    'entity_type' => $entity->getMorphClass(),
                    'entity_id'   => $entity->id,
                ]);
            }

            $this->assertEquals(4, Activity::query()->where('type', '=', 'afridocs_file_upload')->count());

            // Libellé lisible dans le journal d'audit et les listes d'activité
            $activity = Activity::query()->where('type', '=', 'afridocs_file_upload')->first();
            $this->assertEquals('added linked file', $activity->getText());
            // Les traductions d'origine de BookStack restent disponibles
            $this->assertEquals('created page', trans('activities.page_create'));
        });
    }

    public function test_list_returns_files_and_permissions()
    {
        $this->withModule(function () {
            $book = $this->entities->book();
            $this->asEditor();
            $this->upload($book)->assertStatus(201);

            $this->asViewer()->getJson($this->filesUrl($book))
                ->assertOk()
                ->assertJson(['can_upload' => false])
                ->assertJsonPath('files.0.name', 'procedure.txt')
                ->assertJsonPath('files.0.can_delete', false)
                ->assertJsonMissingPath('files.0.path');
        });
    }

    public function test_viewer_cannot_upload_or_delete()
    {
        $this->withModule(function () {
            $page = $this->entities->page();
            $this->asViewer();
            $this->upload($page)->assertStatus(403);

            $this->asEditor();
            $fileId = $this->upload($page)->json('id');

            $this->asViewer()->deleteJson('/afridocs/files/' . $fileId)->assertStatus(403);
            $this->assertDatabaseHas('afridocs_entity_files', ['id' => $fileId]);
        });
    }

    public function test_files_follow_entity_visibility()
    {
        $this->withModule(function () {
            $book = $this->entities->book();
            $this->asEditor();
            $fileId = $this->upload($book)->json('id');

            $this->permissions->setEntityPermissions($book, [], []);

            $this->asViewer();
            $this->getJson($this->filesUrl($book))->assertStatus(404);
            $this->get('/afridocs/files/' . $fileId)->assertStatus(404);
            $this->get('/afridocs/files/' . $fileId . '/view')->assertStatus(404);
        });
    }

    public function test_download_and_inline_view()
    {
        $this->withModule(function () {
            $page = $this->entities->page();
            $this->asEditor();
            $fileId = $this->upload($page, 'rapport.txt')->json('id');

            $download = $this->get('/afridocs/files/' . $fileId);
            $download->assertOk();
            $download->assertHeader('Content-Disposition', "attachment; filename*=UTF-8''rapport.txt");

            $view = $this->get('/afridocs/files/' . $fileId . '/view');
            $view->assertOk();
            $view->assertHeader('Content-Type', 'text/plain; charset=utf-8');
            $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));
        });
    }

    public function test_disallowed_extension_rejected()
    {
        $this->withModule(function () {
            $this->asEditor();
            $this->upload($this->entities->page(), 'script.php')->assertStatus(422);
            $this->assertEquals(0, EntityFile::query()->count());
        });
    }

    public function test_editor_can_delete_and_activity_is_logged()
    {
        $this->withModule(function () {
            $shelf = $this->entities->shelf();
            $this->asEditor();
            $fileId = $this->upload($shelf)->json('id');
            $path = EntityFile::query()->find($fileId)->path;

            $this->deleteJson('/afridocs/files/' . $fileId)->assertOk();

            $this->assertDatabaseMissing('afridocs_entity_files', ['id' => $fileId]);
            $this->assertFileDoesNotExist(storage_path($path));
            $this->assertDatabaseHas('activities', ['type' => 'afridocs_file_delete']);
        });
    }

    public function test_files_removed_when_entity_destroyed()
    {
        $this->withModule(function () {
            $page = $this->entities->page();
            $this->asEditor();
            $fileId = $this->upload($page)->json('id');

            $this->entities->destroy($page);

            $this->assertDatabaseMissing('afridocs_entity_files', ['id' => $fileId]);
        });
    }

    public function test_block_and_assets_rendered_on_entity_views()
    {
        $this->withModule(function () {
            $book = $this->entities->book();
            $resp = $this->asEditor()->get($book->getUrl());

            $resp->assertSee('data-afd-files', false);
            $resp->assertSee($this->filesUrl($book), false);
            $resp->assertSee('/afridocs/assets/afridocs-files.js', false);

            // Le type MIME doit être exécutable, sinon le navigateur refuse le script (nosniff)
            $this->get('/afridocs/assets/afridocs-files.js')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
            $this->get('/afridocs/assets/afridocs-files.css')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/css; charset=utf-8');
            $this->get('/afridocs/assets/functions.php')->assertNotFound();
        });
    }
}
