# Instagram Follower Tracker

Laravel app que verifica de 15 em 15 minutos a lista de followers de perfis Instagram e regista quem entra/sai. Sem admin panel — toda a configuração vive em `config.json` na raiz.

## Como funciona

- `config.json` define credenciais de scraping (sessão de uma conta IG de sacrifício) e a lista de perfis a monitorizar.
- Cronjob → `php artisan schedule:run` → dispara `followers:sync` a cada 15 minutos.
- Para cada perfil configurado, o comando:
    1. resolve `username → user_id` (endpoint `web_profile_info`),
    2. pagina a lista completa de followers (`friendships/{id}/followers/`),
    3. faz diff com o snapshot anterior guardado em SQLite,
    4. regista eventos `follow` / `unfollow` com timestamp e metadados do user.
- Páginas públicas: `/` (grelha de perfis) e `/{username}` (detalhe, gráfico, actividade recente).

## Configuração local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

cp config.example.json config.json
# editar config.json — ver secção "Obter os cookies" abaixo
```

Testar manualmente:

```bash
php artisan followers:sync                # todos os perfis do config.json
php artisan followers:sync algum_username # só um
php artisan serve
```

## Obter os cookies para o config.json

1. Cria (ou usa) uma conta Instagram **de sacrifício** — não uses a tua principal.
2. Faz login em `https://www.instagram.com/` num browser desktop.
3. Abre DevTools → Application → Cookies → `https://www.instagram.com`.
4. Copia os valores destes três cookies para `config.json`:
    - `sessionid` → `instagram.session_id`
    - `csrftoken` → `instagram.csrf_token`
    - `ds_user_id` → `instagram.ds_user_id`
5. `user_agent` deve corresponder ao browser onde fizeste login. Se usaste Chrome no Windows, o valor default do exemplo serve.

Se o comando começar a devolver HTTP 401/403, o `sessionid` expirou ou foi invalidado — repete o processo.

Se der HTTP 429 (rate limit), aumenta `delay_ms_between_pages` no config (por defeito 1500ms entre páginas).

## Deploy no CloudPanel

1. **Cria um site PHP** no CloudPanel → PHP 8.5 (ou 8.3+) → Application: Generic PHP → Document Root: `public`.
2. **Sobe o código** para `~/htdocs/<domain>` (git clone ou upload).
3. **Instala deps** como user do site:
    ```bash
    cd ~/htdocs/<domain>
    composer install --no-dev --optimize-autoloader
    cp .env.example .env
    php artisan key:generate
    touch database/database.sqlite
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
4. **Preenche `config.json`** (mesmo processo dos cookies acima).
5. **Permissões** (o utilizador do site precisa de escrita em `storage/` e `database/`):
    ```bash
    chmod -R ug+rw storage bootstrap/cache database
    ```
6. **Cron** — no CloudPanel, Site → Cron Jobs, adiciona:
    ```
    * * * * * cd /home/<siteuser>/htdocs/<domain> && php artisan schedule:run >> /dev/null 2>&1
    ```
    O `schedule:run` corre a cada minuto e o Laravel encarrega-se de disparar o `followers:sync` só de 15 em 15.
7. Verifica logs em `storage/logs/laravel.log` e `storage/logs/followers-sync.log`.

## Adicionar / remover perfis

Editar `config.json`:

```json
{
    "profiles": ["perfil1", "perfil2", "perfil3"]
}
```

Não é preciso migração nem restart. Na próxima run o comando pega nos novos perfis. Um perfil removido pára de ser actualizado (o histórico fica na DB).

## Notas importantes

- **O primeiro sync de cada perfil é o baseline**: importa a lista actual sem gerar eventos `follow` (evita spam de "+N follows" iniciais). A partir daí, cada delta gera eventos.
- **Refollows**: se alguém deu unfollow e depois voltou a seguir, aparece como novo evento `follow` e o registo de follower é reactivado (o `first_seen_at` original mantém-se).
- **Perfis privados**: se o perfil monitorizado for privado e a conta de scraping não o segue, o IG devolve `is_private=true` sem lista de followers. Follow com a conta de scraping primeiro.
- **Risco de conta**: mesmo com polling de 15 min a conta de scraping pode ser suspensa. É por isso que se usa conta de sacrifício. Ter um segundo `sessionid` de reserva ajuda.

## Estrutura relevante

- `config.json` — única fonte de configuração da aplicação (gitignored)
- `app/Services/TrackerConfig.php` — parse e validação de `config.json`
- `app/Services/InstagramClient.php` — HTTP client autenticado
- `app/Services/FollowerSync.php` — lógica de diff e persistência
- `app/Console/Commands/SyncFollowers.php` — `php artisan followers:sync`
- `routes/console.php` — regista o schedule de 15 em 15 min
- `app/Http/Controllers/ProfileController.php` — rotas públicas
- `resources/views/profiles/` — Blade templates (Tailwind via CDN)
