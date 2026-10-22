# TimonConfigs — następna zmiana panelu członka w Astra Medium

**Status:** etap prezentacji został zaimplementowany w theme 0.9.0 / Core 0.7.0. Wyniki i zakres są w `MEMBER_DASHBOARD_0.9.md`. Poniższe wymagania pozostają referencją; testy rzeczywistego Discorda i hostingu nadal wymagają HTTPS stagingu oraz konfiguracji właściciela.

## Ustalenie właściciela i źródło referencji

Właściciel wskazał coldz.pro oraz cztery zrzuty: przycisk Member login, kartę logowania, ekran zgody Discorda i zalogowany dashboard. Chce podobnego przepływu w późniejszym etapie Astra Medium. Ten dokument zapisuje wymaganie; nie oznacza wdrożenia nowego interfejsu ani aktywacji OAuth.

Próba odczytu publicznych `https://coldz.pro/` i `/login` zakończyła się `Tunnel connection failed: 403 Forbidden` na proxy środowiska. Obserwacje dotyczą wyłącznie dostarczonych zrzutów. Nie zweryfikowano sesji, uprawnień ani zaplecza coldz.pro. Nie kopiuj jego zasobów, danych aplikacji, callbacków lub identyfikatorów użytkowników.

## Docelowy przepływ TimonConfigs

1. Gość widzi w nagłówku wyraźny **Member login**, prowadzący do istniejącego `/account/`.
2. Widok gościa ma centralną kartę: logo TimonConfigs, krótkie wyjaśnienie prywatnej biblioteki, **Continue with Discord**, informację o weryfikacji dostępu przez role oraz **Back to homepage** i linki prawne. Zachowaj głęboką czerń, pomarańczowe CTA i niebieskie akcenty TimonConfigs. Layout powinien działać również na wąskim i niskim ekranie.
3. Przycisk używa istniejącego `TC_Discord::login_url()`. Autoryzację wyświetla prawdziwy Discord dla własnej aplikacji TimonConfigs. Jej nazwa/logo/redirect URI wymagają ustawień właściciela w Discord Developer Portal. Przy braku konfiguracji pokaż istniejący uczciwy stan niedostępności.
4. Po udanym logowaniu `/account/` staje się dashboardem: baner **Welcome back**, rzeczywisty avatar/nazwa, aktualny status dostępu, **Last Checked**, **Refresh Roles**, wymagany **Reconnect Discord** i bezpieczne wylogowanie.
5. Nawigacja i kafelki prowadzą do istniejących funkcji: **View Configs** (`/configs/`), **My Downloads** (`/my-downloads/`), sekcja profilu na `/account/`, **Support** (`/contact/`) i **Browse Memberships** (`/pricing/`). Wykorzystaj istniejące karty członkostwa i rzeczywistą historię pobrań w dalszej części panelu.

Stosuj wyłącznie rzeczywiste stany **Free Member / VIP Monthly / VIP Lifetime**, a także stan niezweryfikowany/niedostępny. Nazwy VIP/VIP+ i odznaki ze zrzutu nie zmieniają oferty TimonConfigs. Uprawnienia wyznacza serwer, a opłaty i role płatne nadal obsługuje Upgrade.chat.

## Co zachować i rozszerzyć

- `TC_Member_Area` już renderuje kartę gościa, dane właściciela, role, historię, bibliotekę i nawigację. Rozbuduj jego układ, bez tworzenia drugiego systemu kont.
- `header.php` już ma warunkowy link Account/Profile; dostosuj jego wygląd i stan po logowaniu.
- `assets/css/member.css` i istniejące ustawienie grafiki panelu są bazą stylowania. Zachowaj edytowalne materiały i spójność z publicznym premium frontendem.
- `TC_Discord`, `TC_Downloads` i `TC_Private_Files` pozostają usługami autoryzacji/dostarczania. Zachowaj walidację state, sesje, szyfrowanie tokenów, jednoznaczne Discord ID, bieżącą weryfikację ról, jednorazowe pobrania i ochronę cache.
- Ekran zgody na zrzucie pokazuje zakres identify; nie dowodzi sposobu weryfikowania ról przez coldz.pro. TimonConfigs zachowuje wymagane `identify guilds.members.read`. Wsparcie PKCE sprawdź na własnej aplikacji podczas rzeczywistych testów stagingowych.

Powiadomienia, rekrutacja staff, liczniki ticketów i przesyłanie skryptów przez członków widoczne u referencji nie są częścią tej zmiany. Nie dodawaj pozornie aktywnych kafelków ani fikcyjnych danych. Istniejący upload administratora oraz Security Bot, Ticket Tool i Upgrade.chat pozostają właściwymi narzędziami swoich funkcji.

## Odbiór późniejszej implementacji

- Gość przechodzi czytelnie od nagłówka do karty i prawdziwej autoryzacji Discorda; działają klawiatura, focus, powrót, anulowanie oraz stan wyłączonej konfiguracji.
- Dashboard pokazuje dane wyłącznie bieżącego właściciela. Zweryfikuj Free, Monthly, Lifetime, brak członkostwa, odebraną rolę, wygasły token i błąd API. UI nie może zmienić decyzji o dostępie.
- Zachowaj `noindex`, wykluczenie sitemap, `private/no-store`, neutralne REST rendering oraz ochronę przed IDOR i replay. Zweryfikuj wykluczenia CDN/page cache na docelowym HTTPS stagingu.
- Kafelki prowadzą do istniejących działających adresów. Nie powstaje drugi checkout, system ról lub system ticketów. Historia i oznaczenia członkostwa odpowiadają rzeczywistym danym.
- Sprawdź 390/768/1440 px, długą nazwę użytkownika, brak avatara, brak historii i błędy weryfikacji. Wykonaj stosowne dotychczasowe testy, rzeczywisty przepływ OAuth na HTTPS stagingu oraz końcowy przegląd bezpieczeństwa w Astra Medium. Dopiero po zgodzie właściciela wdrażaj na produkcję.

Implementację rozpocznij od aktualnego `main`, przeczytaj `PROGRESS.md`, `NEXT_SESSION.md` i `MEMBER_LIBRARY_0.8.md`. Zachowaj istniejący projekt; treści publiczne pisz po angielsku, raporty dla właściciela po polsku.
