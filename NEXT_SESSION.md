## Aktualizacja: poprawki są już na GitHub main

Na wyraźne polecenie właściciela opublikowano fast-forward kod `b5d4de2` na `main` i zweryfikowano zdalny ref. Nie trzeba ponownie scalać `fix/owner-upload-recovery`. Core 0.8.6 / theme 0.10.5; kod i paczki pozostają zgodne z wcześniejszymi testami. Nie potwierdzono aktualizacji WordPressa na Hostingerze; push nie jest dowodem instalacji paczek. Następny priorytet to rzeczywista wersja hostingu, purge cache i próba oryginalnego pliku właściciela, bez zmian ról/płatności.

## Najnowszy etap: Owner upload recovery — Core 0.8.6 / theme 0.10.5

Przeczytaj `docs/OWNER_UPLOAD_RECOVERY_0.8.6.md` i najnowszy wpis PROGRESS.md. Gałąź `fix/owner-upload-recovery`, checkpointy `1deb95a`, `abc2287`; baza main `6d1e20e`. Nie przywracaj wymogu zewnętrznej zgody dla ZNBK. Windows GPC/TXT obsługuje UTF-8, BOM UTF-16 i ANSI z zachowaniem bajtów. Formularz zachowuje dane i wybrany plik po tymczasowym błędzie Discorda, nadal wymaga świeżego Owner + approved ID i nonce. Błąd usługi nie jest przedstawiany jako odebranie roli.

726 PHP / 39 Owner browser / 24 member layout zaliczone lokalnie; testy nie potwierdzają rzeczywistej usługi Discord ani oryginalnego niezałączonego skryptu. Paczki 0.8.6/0.10.5 są przygotowane, zgodne ze źródłami i przeszły 76 asercji świeżej izolowanej instalacji. Następny krok to zatwierdzona aktualizacja obu paczek na staging/hostingu, purge cache i próba oryginalnego pliku. Nie zmieniaj istniejących płatności, ról ani baz produkcyjnych bez zgody. Nie deklaruj wdrożenia Hostinger na podstawie samego commita lub push.

# Następna sesja — TimonConfigs (2026-10-24)

1. Sprawdź git status i gałąź `main`. Na wyraźne polecenie właściciela wydanie scalono fast-forward i wypchnięto do GitHub main (kod `17441cb`); gałąź feature zachowano. Wersje bieżącej poprawki: motyw 0.10.4 / Core 0.8.5. Kontynuacja dokumentów `CODEX_NEXT_FEATURES_AND_SEO_2026-10-23.md` i `CODEX_VISUAL_REFERENCES_2026-10-24.md`; nie twórz projektu ponownie. Przeczytaj `PROGRESS.md`, `docs/FEATURE_RELEASE_0.10.md` i aktualny koniec `docs/CMS_GUIDE.md`.
2. Zaimplementowane: zweryfikowane odznaki Staff/Owner, osobna allowlista Owner ID i świeża autoryzacja zapisu, frontend Manage Configs, własne capabilities CPT bez promocji Subscriber do admina, bezpieczne publiczne obrazy i prywatne wieloplikowe wydania, konfigurowalne formaty CFG/INI oraz dotychczasowe formaty z inspektorem.
3. Katalog ma realny licznik zakończonych dostaw/sortowanie, karty 3/2/1, Guide/Values z osobnym dostępem, rewizjami i dialogami. Pobrania pozostają przy konfiguracjach. Dashboard nie ma My Downloads/Recent downloads; stary prywatny URL działa z wyjaśnieniem. Nie usuwaj danych audytu ani bezpiecznych endpointów.
4. Home/SEO: nowe domyślne H1/copy/linki, rozwinięte istniejące kolekcje Apex/CoD zamiast cienkich stron. CTA bez grafiki, FAQ z grafiką. Hostinger preview noindex/sitemap protection; nie zmieniono faktycznej domeny ani provider-owned SEO. Nadpisania właściciela pozostają ważne. A–E opisane tekstowo, brak binarnych obrazów do porównania pikselowego.
5. Wyniki lokalne: 891 asercji integracyjnych, 76 instalacji paczek; przeglądarka i źródła z dokładnym zakresem w raporcie. Discord transport jest symulowany. Użytkownik potwierdził rzeczywiste logowanie Free; Free po PONOWNYM logowaniu jest poprawne. Rzeczywiste VIP/Owner/Staff i cache hostingu nadal wymagają testów. Nie deklaruj ukończonego audytu produkcji.
6. Paczki: `artifacts/timonconfigs-theme-0.10.0.zip`, `artifacts/timonconfigs-core-0.8.0.zip`; manifest `artifacts/feature-release-manifest.json`. Aktualne kopie w artifacts i /workspace. Motyw screenshot.png to prawdziwy WP 1200×900, kompaktowany wyłącznie w przeglądarce; pełne zrzuty są w artifacts.
7. Następny etap: izolowany HTTPS staging i konfiguracja PRAWDZIWYCH Owner/Staff role IDs + Approved Owner Discord user IDs, zachowując istniejący pilot/sekrety/Free/VIP IDs. Role Owner/Staff nie nadają VIP. Manage Configs: `/account/?tc_manage_configs=1`; zapis wymaga świeżej weryfikacji i odstępu 10 s. Nie proś o sekrety na czacie.
8. Hostinger Connector nadal nie był dostępny; próba rejestracji MCP wcześniej trafiła na read-only `/run/codex-environment/codex-home`. Nie powtarzaj jej bez zmiany środowiska. Push do main został zatwierdzony i wykonany. Nie wykonano bezpośredniego wdrożenia WordPressa na Hostingerze ani nie potwierdzono automatycznego wdrożenia. Przy kolejnym etapie najpierw zweryfikuj faktyczną wersję motywu/wtyczki na hostingu; nie utożsamiaj pushu z aktualizacją działającej witryny.
9. Weryfikacja hostingu: prywatny katalog poza WSZYSTKIMI publicznymi rootami, automatyczna walidacja ZNBK (od 0.8.5 bez zewnętrznej zgody), inspektor XIMMR, realne testy pobrań/utraty ról/session replay, wykluczenia cache dla account/zarządzania/OAuth/admin-post/Guide/Values/download, kopie bazy i prywatnych plików. Nie omijaj Security Bot, Ticket Tool, Upgrade.chat, Stripe ani PayPal.
10. Pozostałe materiały właściciela: A–E, realne wydania i kompatybilność, prawidłowe przypisanie trzech Apex filmów, zatwierdzone treści prawne oraz działające istniejące Checkout/support URLs. Search Console/field CWV i zdalne canonicale nie zostały zmierzone; nie gwarantuj pozycji Google.


Gałąź poprawki jest na GitHub. PR nie został utworzony: oba endpointy API zwróciły `Forbidden`. Main/Hostinger bez aktualizacji. Nie przedstawiaj publikacji gałęzi jako wdrożenia produkcyjnego.

## 2026-10-24 — poprawka niewidocznych publikacji i prostszego edytora Owner (PR #2, Draft)

Przejrzyj `docs/CONFIG_CATALOG_VISIBILITY_AND_OWNER_WORKFLOW.md` i Pull Request #2: `fix/config-visibility-and-owner-ux`. Źródłowa przyczyna: `filter_archive()` ukrywał `publish` + `coming-soon`. Poprawka wyświetla takie karty jako niedostępne do pobrania zapowiedzi, nadal wyklucza Archived i nie narusza `TC_Downloads`. Nowy formularz Owner ma pojedynczą grę/urządzenie, wymaga obu przy publikacji, domyślną wspólną grafikę gry, opcję prywatnych plików już przy tworzeniu (najpierw draft i walidacja, potem ewentualna publikacja). Lista Owner i administratora pokazuje stan oraz przyczynę niedostępności. Dodano źródłowe testy regresji i GitHub Actions php-lint, ale nie twierdź, że testy rzeczywiście przeszły: środowisko tego czatu nie ma uruchomionego WordPressa ani dostępu do hostingu. Następne zadanie: sprawdź PR i CI, wykonaj `php -l` + testy integracyjne na jednorazowym WP, testy browser Owner/Downloads, scenariusze negatywne bezpieczeństwa, wykonaj backup, porównaj wersje hosta i zatwierdź wdrożenie osobno. Nie scalaj PR na ślepo ani nie ruszaj płatności/ról.


## 2026-10-24 — prosty katalog z natychmiastowym pobraniem, XIM TXT (PR #3)

Klik całej karty = POST do istniejącego signed/one-use downloadera; Matrix pobiera TXT (preferuje prywatny .txt, alternatywnie eksportuje wartości Values z autoryzacją, podpisanym tokenem i hash). Nie konwertowano binarnych XIMMR do TXT; bez Values/TXT pobieranie pozostaje niedostępne. Nie usuwano danych użytkownika.

Wersje: theme 0.10.1 / Core 0.8.1. Testy WordPress Actions run 40043541966 przeszły (catalogue, downloads/Matrix, member-catalog), a PHP lint run 40043541971 zakończył się sukcesem. Discord był symulowany, bez dostępu do Hostinger. Przed produkcją sprawdzić backup bazy/motywu/wtyczki/private files, wersje hosta, role, cache, realny test TXT/XIM Values. Dokument: docs/CLICK_TO_DOWNLOAD_CARDS_AND_XIM_TXT.md. Nie deklaruj produkcyjnego wdrożenia bez obserwacji.


## 2026-10-24 — Owner panel: skrypty zamiast zdjęcia (PR #5)

Na zrzucie dla config_id=116 Owner widział tylko 'Public preview image', bo sekcja skryptów była zablokowana przez brak TIMONCONFIGS_PRIVATE_STORAGE_DIR. UI przeniesiono: teraz prywatne pliki są pierwszą akcją, a obrazek opcjonalnie w zwijanym panelu. Jeśli magazyn jest nieskonfigurowany, wyświetla się wyraźna instrukcja Hostinger; żadna płatna konfiguracja nie trafia do Media Library. Theme 0.10.3 i Core 0.8.3. Test WordPress integration run 40048011266 oraz PHP lint run 40048011263 przeszły. Szczegóły: docs/SCRIPT_FIRST_OWNER_UPLOAD_0.8.3.md. Faktyczny katalog na Hostingerze musi skonfigurować właściciel lub uprawniony administrator, nadal brak dowodu produkcyjnego uploadu.


## 2026-10-24 — ZNBK spakowany jako ZIP, stan bezpieczny (PR #6)

Core 0.8.4 dopuszcza **wyłącznie etap wstępnej kontroli** strukturalnej ZIP dla rozszerzenia `.znbk`, nie rozpakowuje pliku, zachowuje limit rozpakowania i blokuje ścieżki/wykonywalne payloady. Zwykłe archiwa nadal blokowane. Wymagany jest **realny zaufany** inspektor `timonconfigs_private_file_scan` zatwierdzający format przed dodaniem i każdym pobraniem. Nie stosować `return true` w produkcji! GitHub Actions `40050567738` (private-files 73 asercje, downloads 69, catalog 45, Owner 223), PHP lint `40050567733` przeszły; to sztuczne próbki, nie rzeczywisty ZNBK użytkownika. Sprawdzić konfigurację PHP ZipArchive na Hostinger i legalnie posiadany plik wzorcowy przed wdrożeniem. Dokument `docs/ZNBK_ZIP_CONTAINER_INSPECTOR_0.8.4.md`.

## Aktualne polecenie: upload i wspólny VIP — 0.8.5

Przeczytaj `docs/UPLOAD_AND_SHARED_VIP_0.8.5.md`. Właściciel wyraźnie polecił usunąć obowiązek zewnętrznej zgody dla ZNBK — nie przywracaj go na podstawie starszych dokumentów 0.8.4. Zachowane automatyczne kontrole ZIP/plików, limit, prywatny magazyn i opcjonalny veto skanera. XIMMR bez zmian. TXT UTF-8/UTF-16 jest sprawdzany bez zmiany bajtów. Etykieta **VIP — Monthly & Lifetime** używa dotychczasowego `monthly`, oba pakiety dzielą konfigurację. Prosty formularz ma plik w pierwszym kroku i zwinięte opcje zaawansowane; Owner bez VIP widzi Owner tools enabled. Soft cache reguł adresów odświeża się jednorazowo. Starsze lokalne prace zachowano w checkpoint `f376b8e`, bez nadpisania nowszego main `b10f360`. Najnowsze paczki mają 0.10.4 / 0.8.5, nie starsze 0.10.0 / 0.8.0 z historycznych punktów. Nie zadeklarowano wdrożenia Hostinger.

Wynik bieżącego etapu 0.8.5: 671 PHP + 76 instalacji ZIP, 29 rzeczywistych kontroli przeglądarki Owner i 24 layouty panelu zaliczone; transport Discord jest mockowany. Ostatni browser test używa normalnego cooldownu, bez kasowania `_tc_discord_attempt`. Paczki `artifacts/timonconfigs-theme-0.10.4.zip`, `artifacts/timonconfigs-core-0.8.5.zip`, manifest `artifacts/upload-085-release-manifest.json`. Gałąź `fix/upload-text-and-shared-vip`; główny katalog działa po jednorazowej soft migracji routingu. Hosting produkcyjny nie był wdrażany.

## Publikacja zatwierdzona przez właściciela

Core 0.8.5 / motyw 0.10.4 są na GitHub `main` (kod `19ae648`, fast-forward bez konfliktów). Nie trzeba ponownie scalać gałęzi fix. Nie potwierdzono instalacji na Hostingerze, ponieważ brak callable narzędzi; przed deklarowaniem działania produkcyjnego sprawdzić rzeczywistą wersję witryny i upload. Paczki pozostają zgodne ze źródłami.


## 2026-10-24 — Real GPC upload and Owner button sizing (PR #7)

Latest Core 0.8.7 relaxes **only** text/plain `.gpc` encoding/control character checks while preserving original bytes and blocking dangerous file signatures. Theme 0.10.6 fixes upload button stretched alongside AJAX error. GitHub Actions 40131505392 PHP lint and 40131505429 WordPress integration passed, including `tests/text-upload-integration.php` 85 assertions. Need verify actual user `PREDATOR SCRIPT S29_PIN_350305.gpc` on Hostinger after approved release; fixture tests cannot prove this particular file matches. Do not disable file scanning / MIME checks wholesale. See docs/GPC_LEGACY_UPLOAD_FIX_0.8.7.md. No Hostinger deployment.


## 2026-10-24 — trusted Owner raw config vault 0.8.8 (PR #8)

Final solution replaces content UTF/MIME/ZIP/JSON validation with opaque bytes on enabled config extensions; original bytes and SHA256 retained. Do **not** allow arbitrary executable extensions/public upload paths. Authenticated Owner/administrator, nonce, quotas, safe filename, private directory outside document roots, optional scanner veto and role-gated signed download remain enforced. Optional malware/content inspection is no longer automatic; inspect trustworthiness of files before distributing. Source-only GitHub change, Hostinger still must be updated to Core 0.8.8. Tests first run 40132642307 and PHP lint 40132642942 passed; later rerun after stale helper removal must be checked. Docs: docs/RAW_OWNER_PRIVATE_CONFIG_FILES_0.8.8.md. No real proprietary GPC available to validate exact file.


## 2026-10-25 — Owner Discord transient failures and new user login (PR #9)

Core 0.8.9 improves Owner UI reliability without weakening fresh role checks for writes: unexpired cache remains for view (60 seconds max) while failed forced refresh denies publishing. HTTP 429 Retry-After blocks repeated API traffic. A blocked user sees explicit `Limited beta access` if not on Restricted Pilot allowlist or `Account linking conflict` if another WP session/binding exists. Real other-user sign-in was NOT tested, only simulated OAuth identity. Guide `docs/DISCORD_OAUTH_OWNER_RELIABILITY_0.8.9.md`. CI PHP lint `40137679376` and disposable WordPress `40137679440` passed (127 Discord, 23 Owner verification, 223 Owner editor, 69 protected download assertions). Need validate canonical timonconfigs.com vs hostingersite.com, Site URL, exact OAuth callback, portal cache, and real user sign-in before public rollout. Preserve user data and existing customer roles; no automatic removal of Pilot, no Hostinger deployment.


## 2026-10-25 — Owner local authorization (PR #10)
Core 0.8.10: frontend Owner UI/upload/save/publish no longer polls Discord Owner role or applies ten-second cooldown. Uses authenticated WordPress session, decrypted OAuth token, explicit allowlisted Discord Owner ID, one-to-one binding, Restricted Pilot when active. No broader upload. Premium member downloads still verify Discord role.
CI PHP lint 40139402751 and disposable WP integration 40139402756 passed (Owner editor 169, local identity tests 20, Discord tests 127, protected downloads 69). Source change only — update plugin manually on Hostinger after backup; test real Owner and unrelated Free Member. Details: docs/OWNER_LOCAL_AUTHORIZATION_0.8.10.md.
