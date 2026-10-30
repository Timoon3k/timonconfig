# Przegląd eksportu Rank Math — 2026-10-30

Źródło: plik użytkownika `timonconfigs-rank-math-seo-optimized-2026-10-30.json`. Traktowano go jako dane konfiguracyjne. Zalecany import SEO: [timonconfigs-rank-math-seo-only-2026-10-30.json](timonconfigs-rank-math-seo-only-2026-10-30.json). Pełna poprawiona kopia eksportu do archiwum: [timonconfigs-rank-math-reviewed-2026-10-30.json](timonconfigs-rank-math-reviewed-2026-10-30.json).

## Co można potwierdzić z eksportu

- `titles.robots_global` zawiera `index`; domyślne strony i konfiguracje również mają index. Eksport sam w sobie nie wskazuje globalnego zakazu indeksowania.
- Moduł sitemap oraz mapy XML stron, konfiguracji, gier i urządzeń są włączone. Puste taksonomie są wyłączane z indeksowania; prywatne pliki i konta nie powinny trafiać do wyszukiwarki.
- Główny URL to `https://timonconfigs.com/`. W odczytanych ustawieniach nie ma domeny hostingersite.com.
- Eksport nie zawiera treści strony, indywidualnych metadanych wpisów/terminów, WordPress `blog_public`, nagłówków HTTP, faktycznego robots.txt, mapy XML ani raportu Google Search Console. Nie dowodzi wdrożenia tych ustawień, indeksowania lub pozycji w Google.
- Pola `facebook_secret` i `maps_api_key` są puste. Nie zmieniano modułów, uprawnień użytkowników, przekierowań ani ustawień płatności. Struktura `modules` pozostaje obiektem z numerycznymi kluczami i przerwą w numeracji, zgodnie z oryginałem.

## Dokładne zmiany

| Opcja | Poprzednio | Po zmianie |
| --- | --- | --- |
| `general.breadcrumbs_home_label` | Strona główna | Home |
| `general.breadcrumbs_archive_format` | Archiwum dla %s | Archives for %s |
| `general.breadcrumbs_search_format` | Wyniki dla %s | Search results for %s |
| `general.breadcrumbs_404_label` | Błąd 404: strona nie została znaleziona | Error 404: page not found |
| `titles.homepage_title` | XIM Matrix & Cronus Zen Apex Legends Configs &#124; TimonConfigs | XIM Matrix Apex Legends Configs &#124; TimonConfigs |
| `titles.homepage_description` | Explore Apex Legends configs for XIM Matrix and Cronus Zen. Browse controller and MnK setups, Free and VIP scripts, installation guides and updates. | Explore XIM Matrix Apex Legends setup guides and Cronus Zen configs. Compare devices, view release details and join the TimonConfigs Discord community. |
| `titles.opening_hours` | 09:00–17:00 przez 7 dni (niepotwierdzone) | [] — brak deklaracji godzin |
| `titles.pt_post_title` | %title% %sep% %sitename% | %title% %page% %sep% %sitename% |
| `titles.pt_page_title` | %title% %sep% %sitename% | %title% %page% %sep% %sitename% |
| `titles.pt_tc_config_title` | %title% %sep% %sitename% | %title% %page% %sep% %sitename% |
| `titles.tax_category_title` | %term% %sep% %sitename% | %term% %page% %sep% %sitename% |
| `titles.tax_tc_game_title` | %term% Configs & Setup Guides &#124; TimonConfigs | %term% Configs & Setup Guides %page% &#124; TimonConfigs |
| `titles.tax_tc_device_title` | %term% Configs & Setup Guides &#124; TimonConfigs | %term% Configs & Setup Guides %page% &#124; TimonConfigs |
| `sitemap.include_featured_image` | off | on |

Tytuł Home skupia się na frazie **XIM Matrix Apex Legends**. Fraza **Cronus Zen Discord** otrzymuje użyteczną sekcję społeczności na istniejącym Contact (`/contact/#community`), tytuł i opis Contact oraz rzeczywisty link zaproszenia. Fragment `#community` nie tworzy osobnej indeksowanej podstrony. Nie dodano powtarzanych fraz do wszystkich tytułów.

`%page%` rozróżnia strony paginacji; nie zmienia URL-i. Angielskie etykiety okruszków nie włączają okruszków — `breadcrumbs` pozostaje `off`. Włączenie obrazów wyróżniających w mapie może pomóc w odkrywaniu publicznych obrazów, lecz nie gwarantuje indeksowania. Usunięto niepotwierdzone godziny działania; nie wpisano wymyślonych danych firmy ani ocen klientów.

## Wpływ importu i ograniczenia

Przed importem zachowaj aktualny eksport Rank Math. Zaimportuj **plik SEO-only** w **Rank Math SEO → Status & Tools → Import & Export**. Zawiera pełne sekcje `general`, `titles` i `sitemap`; pomija `modules`, `role-manager` oraz `redirections`, więc nie odtwarza ich dawnych ustawień. Publiczny kod importera Rank Math potwierdza możliwość importu tylko tych sekcji.

**Importer zastępuje całe niepuste sekcje opcji, a nie tylko 14 zmienionych kluczy.** Oba warianty mogą więc zastąpić edycje ustawień ogólnych/tytułów/map wprowadzone po wykonaniu oryginalnego eksportu. Pełny wariant archiwalny zawiera ponadto oryginalne moduły i uprawnienia — jego import ponownie je zastosuje. Nie używaj pełnego wariantu, jeśli nie zamierzasz odtworzyć także tych sekcji. Import nie zmienia WordPress `blog_public`, nie publikuje treści i nie zgłasza strony do Google.

**Ważne przy statycznej stronie głównej:** zweryfikowany publiczny kod Rank Math wybiera dla niej klasę `Singular`, która odczytuje indywidualne `rank_math_title`/`rank_math_description`, a następnie szablony `pt_page_*`. Klasa `Blog` wykorzystuje globalne `homepage_title`/`homepage_description`. Sam import JSON nie ustawia zatem indywidualnego tytułu i opisu statycznej Home. **Core 1.1.11 dodaje adapter**, dzięki któremu statyczna Home bez autorskich metadanych korzysta kolejno z `tc_seo_*`, globalnych ustawień Home i domyślnych tekstów motywu. Autorskie metadane Rank Math nadal mają pierwszeństwo. Dla statycznej Home sprawdź jej edytor Rank Math; zaktualizuj stare indywidualne teksty, jeśli mają być zastąpione. Ustawienia globalne nie edytują indywidualnych tytułów, canonical ani `noindex` już zapisanych dla konkretnej strony/konfiguracji/terminu.

Po aktualizacji Core i imporcie zapisz **Rank Math SEO → Sitemap Settings**, aby odświeżyć cache XML, a następnie wyczyść cache witryny/hostingu. Sprawdź renderowane title/description/robots/canonical Home oraz podstron i mapę `https://timonconfigs.com/sitemap_index.xml`. Core 1.1.11 poprawia obsługę częściowych obiektów `stdClass` w filtrze natywnej mapy Rank Math oraz wyklucza strony użytkowe wskazane w ustawieniach WooCommerce. Potwierdź, że konta, pobrania, koszyk i checkout nie trafiają do publicznej mapy.

Według odpowiedzi właściciela inspekcja Home pokazuje **„Adres URL jest w Google”**. Nie ustalono pozycji ani CTR dla docelowych fraz. W Search Console wykonaj osobne inspekcje Contact i opublikowanych kolekcji oraz sprawdź raport skuteczności. Jeśli konkretny URL jest niezaindeksowany, decydujący będzie wskazany w Search Console powód, a nie sama punktacja Rank Math.

## Walidacja pliku

Oba JSON-y przechodzą odczyt/zapis bez błędów. W pełnym wariancie wszystkie klucze i sekcje oryginału zachowano; wariant SEO-only zawiera identyczne trzy pełne sekcje ustawień, bez modułów/ról/przekierowań. Skrypt porównania potwierdził wyłącznie 14 opisanych zmian, zachowanie globalnego index, modułów, ról i przekierowań.

Lokalny test rzeczywistego importera **Rank Math 1.0.280: 12 asercji zakończonych powodzeniem**. Potwierdzono zapis trzech pełnych sekcji, zachowanie modułów/uprawnień, brak połączeń zewnętrznych i odwracalny backup ustawień fixture. Test przeglądarkowy przeszedł 158 asercji obejmujących 9 widoków HTML i 7 map XML; potwierdzono Home/Contact, ochronę konta i pominięcie prywatnych/płatniczych URL-i w mapach. Regresje WordPressa: 85 asercji. Po testach przywrócono poprzednie lokalne opcje Rank Math i wyłączono testową wtyczkę. Lokalna zależność pochodzi z publicznego commitu wskazanego niżej; z powodu nieaktualnej mapy autoload odtworzono wygenerowane pliki Composer, bez modyfikowania logiki Rank Math, oraz użyto standardowej opcji pominięcia opcjonalnego połączenia konta. Nie oznacza to testu niezmienionego instalacyjnego ZIP-a WordPress.org ani wersji produkcyjnej.

Nie przeprowadzono importu na produkcji. Zewnętrzny odczyt witryny i Google został zatrzymany przez proxy środowiska; nie otrzymano odpowiedzi serwera TimonConfigs. Nie zmierzono rankingów ani Core Web Vitals. Ustawienia i treści wspierają SEO, lecz nie zapewniają wysokiej pozycji lub TOP 1.


## Sprawdzone publiczne źródła Rank Math

Kod odczytano 2026-10-30 z publicznej gałęzi `master`, commit **`50fc59587112316ad754ca3c9a26e1282795d49d`** (Rank Math 1.0.280); nie jest to dowód wersji zainstalowanej na produkcji. Poniższe linki wskazują ten sam utrwalony commit:

- [Import_Export_Settings::do_import_data / set_options](https://github.com/rankmath/seo-by-rank-math/blob/50fc59587112316ad754ca3c9a26e1282795d49d/includes/modules/status/class-import-export-settings.php#L82): każda przekazana, niepusta sekcja jest zapisywana przez `update_option`; osobna sekcja `role-manager` odtwarza capabilities.
- [Paper::get_papers](https://github.com/rankmath/seo-by-rank-math/blob/50fc59587112316ad754ca3c9a26e1282795d49d/includes/frontend/paper/class-paper.php#L122): statyczna Home korzysta z `Singular`, a strona wpisów z `Blog`.
- [Singular::get_post_title / get_post_description](https://github.com/rankmath/seo-by-rank-math/blob/50fc59587112316ad754ca3c9a26e1282795d49d/includes/frontend/paper/class-singular.php#L194): indywidualne metadane mają pierwszeństwo przed szablonem typu wpisu.
- [Blog::title / description](https://github.com/rankmath/seo-by-rank-math/blob/50fc59587112316ad754ca3c9a26e1282795d49d/includes/frontend/paper/class-blog.php#L28): globalne ustawienia `homepage_title` i `homepage_description`.
