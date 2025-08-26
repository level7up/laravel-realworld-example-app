## Backend setup and run

- **Requirements**
  - PHP 8.0+
  - Composer
  - SQLite (or configure your DB in .env)
  - Node (optional, for frontend)

- **Initial setup**
  - Copy env and generate key:
    - `cp .env.example .env`
    - `php artisan key:generate`
  - Configure database in `.env` (SQLite is pre-wired at `database/database.sqlite`). If missing, create the file:
    - `touch database/database.sqlite`


- **Install dependencies**
  - `composer install`


- **Run the API server**
  - `php artisan serve`
  - Or use your local web server (e.g., Herd) at `http://laravel-realworld-example-app.test`

- **Run tests**
  - `php artisan test`


## Revision feature overview

- **What it does**
  - Tracks historical versions of an `Article` as `ArticleRevision` records whenever an article is updated.
  - Exposes endpoints to:
    - List all revisions of an article
    - Fetch a single revision
    - Revert an article to a specific prior revision (owner-only)

- **Endpoints**
  - `GET /api/articles/{article}/revisions`
    - Auth required
    - Response:
      - `{ revisions: ArticleRevision[], revisionsCount: number }`
  - `GET /api/articles/{article}/revisions/{articleRevision}`
    - Auth required
    - Response:
      - `{ article_revision: ArticleRevision }`
  - `POST /api/articles/{article}/revisions/{articleRevision}/revert`
    - Auth required, owner-only
    - Replaces the current article’s title/slug/description/body with the chosen revision’s values
    - Response:
      - `{ article: Article }`

- **JSON shapes**
  - Article includes `revisions` as a wrapped collection:
    - `article.revisions = { revisions: [...], revisionsCount: number }`
  - ArticleRevision fields:
    - `id`, `revision_number`, `slug`, `title`, `description`, `body`, `createdAt`, `updatedAt`
    - `author`: `{ username, bio, image, following }`

- **Authorization rules**
  - All revision routes are inside the `auth` middleware group:
    - Unauthenticated requests receive `401` (ensure `Accept: application/json` header to avoid redirect errors).
  - Revert is restricted to the article owner:
    - Non-owners receive `403`.
  - Safety: Accessing a revision that does not belong to the given article yields `404`.

- **Observer behavior (ArticleObserver)**
  - On Article updated:
    - Creates a new `ArticleRevision` with the article’s pre-update state.
    - `revision_number` is `count(article_id) + 1`.
  - On revert:
    - Observer is intentionally skipped (route is named `articles.revisions.revert`) to avoid creating a new revision for a revert operation itself.

- **Assumptions and decisions**
  - Revisions are created only on updates, not on initial create.
  - Reverting does not produce a new revision record (by design), to keep history consistent and avoid churn on a revert.
  - Revisions are simple snapshots of core fields (title, slug, description, body) and the author at that time.
  - Collection response returns both items and count; no pagination is currently implemented.

- **Testing notes**
  - Feature coverage includes:
    - Listing, showing, and reverting revisions (happy paths)
    - AuthN failures for all three endpoints (`401`)
    - AuthZ failure for non-owner reverts (`403`)
    - Mismatched article/revision lookups (`404`)
    - Observer storing revision on update, and not storing on revert
  - Run with:
    - `php artisan test tests/Feature`

- **Frontend integration (heads-up)**
- First change the `VITE_API_HOST` in `.env` file in the front end app and use your own backend server

  - The frontend page `src/pages/ArticleRevisions.vue` consumes:
    - `GET /api/articles/{slug}` to read `article.title` and `article.author`
    - `GET /api/articles/{slug}/revisions` to render the revisions table
    - `POST /api/articles/{slug}/revisions/{id}/revert` to revert and navigate back to the updated article
  - Frontend expects `createdAt/updatedAt` in camelCase in revision responses and uses `author.username` for display.
