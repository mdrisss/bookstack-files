# BookStack Files

Package Laravel pour [BookStack](https://www.bookstackapp.com/) qui permet de lier des fichiers
(PDF, documents bureautiques, schémas, configurations, images…) aux **étagères, livres, chapitres et pages**.

- Bloc « Fichiers liés » dans la barre latérale, chargé de façon asynchrone.
- Ajout par clic ou glisser-déposer, suppression, téléchargement.
- Aperçu dans un popup pour les PDF, textes, images et médias.
- Ajouts et suppressions tracés dans le journal d'audit (`bookstack_files_upload`, `bookstack_files_delete`).
- **Aucun fichier de BookStack n'est modifié** : le package passe uniquement par les points d'extension
  publics de BookStack (`Theme::listen` et blocs de vue) et par sa propre table.

Compatibilité : BookStack **v26.x** (Laravel 12, PHP ≥ 8.2).

## Installation

Depuis la racine de BookStack :

```bash
composer require mdrisss/bookstack-files --update-no-dev
php artisan migrate
```

Le package est découvert automatiquement par Laravel : il n'y a rien d'autre à configurer.

Ensuite, dans **Paramètres > Rôles**, cochez les permissions *Pièces jointes* (créer / supprimer)
pour les rôles concernés. Par défaut, le rôle « Editor » ne les a pas.

> Tant que le package n'est pas publié sur Packagist, déclarez d'abord le dépôt GitHub :
> `composer config repositories.bookstack-files vcs https://github.com/mdrisss/bookstack-files`

## Droits

| Action | Condition |
|---|---|
| Voir / télécharger / prévisualiser | Pouvoir voir l'élément |
| Ajouter | `attachment-create-all` **et** droit de modifier l'élément |
| Supprimer | `attachment-delete-all` (ou `-own` pour ses propres fichiers) **et** droit de modifier l'élément |

Les permissions par élément (restrictions sur un livre, une étagère…) s'appliquent aussi aux fichiers.

## Configuration

| Variable `.env` | Rôle | Défaut |
|---|---|---|
| `FILE_UPLOAD_SIZE_LIMIT` | Taille maximale d'un fichier, en Mo (réglage BookStack) | `50` |
| `BOOKSTACK_FILES_EXTENSIONS` | Extensions autorisées, séparées par des virgules | voir `EntityFileService::DEFAULT_EXTENSIONS` |
| `STORAGE_ATTACHMENT_TYPE` | Stockage, le même que les pièces jointes BookStack (local, S3…) | `local` |

Les fichiers sont rangés dans `uploads/files/bookstack-files/AAAA-MM/`.

PHP et le serveur web doivent accepter la même taille :

- `php.ini` : `upload_max_filesize = 50M` et `post_max_size = 55M` ;
- Nginx : `client_max_body_size 55M;`.

## Mettre à jour BookStack

`composer require` ajoute le package dans les fichiers `composer.json` et `composer.lock` de BookStack.
Lors d'une mise à jour de BookStack, il faut donc :

```bash
git checkout composer.json composer.lock   # revenir aux fichiers d'origine
git pull origin release                    # mise à jour BookStack habituelle
composer install --no-dev
composer require mdrisss/bookstack-files --update-no-dev
php artisan migrate
```

## Mise à niveau depuis la v1

La v2 renomme les noms internes pour correspondre au package (namespace `Mdrisss\BookstackFiles`,
routes `/bookstack-files/...`, table `bookstack_files`, variable `BOOKSTACK_FILES_EXTENSIONS`).

```bash
composer require mdrisss/bookstack-files:^2.0 --update-no-dev
php artisan migrate --force
```

La migration renomme la table `afridocs_entity_files` et les événements d'audit existants : aucun fichier n'est perdu.
Si `AFRIDOCS_FILES_EXTENSIONS` était défini dans le `.env`, renommez-le en `BOOKSTACK_FILES_EXTENSIONS`.

## Désinstallation

```bash
composer remove mdrisss/bookstack-files --update-no-dev
```

La table `bookstack_files` et les fichiers déjà stockés sont conservés.

## API interne (appelée par `resources/assets/bookstack-files.js`)

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/bookstack-files/entities/{type}/{id}/files` | Liste (JSON) |
| POST | `/bookstack-files/entities/{type}/{id}/files` | Ajout (`multipart`, champ `file`) |
| GET | `/bookstack-files/files/{id}` | Téléchargement |
| GET | `/bookstack-files/files/{id}/view/{nom}` | Affichage en ligne (popup) |
| DELETE | `/bookstack-files/files/{id}` | Suppression |

`{type}` vaut `bookshelf`, `book`, `chapter` ou `page`.

## Tests

Les tests utilisent l'outillage de test de BookStack. Ils ne sont pas inclus dans le paquet installé
en production : en développement, installez le package depuis un clone local
(`composer config repositories.bookstack-files path ../bookstack-files`), puis depuis la racine de BookStack :

```bash
composer refresh-test-database
vendor/bin/phpunit vendor/mdrisss/bookstack-files/tests
```
