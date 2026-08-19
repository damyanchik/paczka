Repozytorium zawiera rozwiązanie zadania obejmującego:

- code review i refaktoryzację dostarczonego kodu legacy,
- bezpieczniejsze naliczanie promocji,
- odnowienia subskrypcji z ochroną przed podwójnym obciążeniem klienta,
- REST API dla subskrypcji z wygasającymi kartami,
- projekt procesu powiadamiania klientów o wygasających kartach,
- testy automatyczne,
- analizę statyczną i kontrolę jakości kodu,
- środowisko Docker,
- pipeline CI.

Przy realizacji starałem się przede wszystkim priorytetyzować problemy według ich wpływu na klienta i biznes, a dopiero później według czystości technicznej kodu.

---

# Uruchomienie

## Docker

Aplikację można uruchomić jedną komendą:

```bash
docker compose up --build
```

Docker Compose:

1. buduje obraz aplikacji PHP,
2. uruchamia MySQL,
3. oczekuje na gotowość bazy danych,
4. wykonuje migracje,
5. uruchamia aplikację Laravel.

Aplikacja będzie dostępna pod:

```text
http://localhost:8000
```

Zatrzymanie kontenerów:

```bash
docker compose down
```

Usunięcie również wolumenu bazy danych:

```bash
docker compose down -v
```

Pełną listę endpointów można sprawdzić poleceniem:

```bash
docker compose exec app php artisan route:list
```

Zarejestrowane zadania cykliczne:

```bash
docker compose exec app php artisan schedule:list
```

Odnowienie subskrypcji można również uruchomić ręcznie:

```bash
docker compose exec app php artisan subscriptions:renew
```

---

# Testy

Testy wewnątrz Dockera:

```bash
docker compose exec app php artisan test
```

lub lokalnie:

```bash
php artisan test
```

Testami objąłem przede wszystkim krytyczne reguły biznesowe, m.in.:

- naliczanie rabatu procentowego,
- naliczanie rabatu kwotowego,
- ograniczenie rabatu do wartości koszyka,
- obsługę rabatu 100%,
- odrzucenie wygasłej promocji,
- limit wykorzystania promocji,
- blokadę ponownego użycia tej samej promocji dla tego samego koszyka,
- walidację API promocji,
- statystyki promocji,
- idempotentne odnawianie subskrypcji,
- ochronę przed dwukrotnym obciążeniem za to samo odnowienie,
- utworzenie zamówienia po udanym odnowieniu,
- wyszukiwanie kart wygasających w ciągu najbliższych 30 dni.

Nie dążyłem do 100% code coverage — priorytetem były scenariusze, których błąd miałby największy wpływ na klienta lub biznes.

---

# Jakość kodu

Projekt wykorzystuje niewielki zestaw narzędzi pokrywający najważniejsze obszary kontroli jakości:

- **PHPUnit** – testy automatyczne,
- **PHPStan + Larastan** – analiza statyczna,
- **Laravel Pint** – kontrola stylu kodu,
- **Rector** – dodatkowa analiza i automatyczne refaktoryzacje,
- **Composer Audit** – kontrola znanych podatności zależności.

Pełną kontrolę jakości można uruchomić poleceniem:

```bash
make check
```

Polecenie wykonuje:

```text
Composer Audit
      ↓
Pint
      ↓
PHPUnit
      ↓
PHPStan / Larastan
      ↓
Rector --dry-run
```

Dostępne są również pojedyncze polecenia:

```bash
make audit
make pint
make test
make stan
make rector
```

Automatyczne formatowanie / refaktoryzacje:

```bash
make fix
```

`make check` nie modyfikuje kodu i ta sama komenda jest wykorzystywana przez CI.

---

# Część 1 – code review

Dostarczony `legacy_promo.php` przeanalizowałem pod kątem przede wszystkim:

- bezpieczeństwa,
- ryzyka finansowego,
- spójności danych,
- współbieżności,
- niezawodności płatności,
- czytelności i utrzymywalności kodu.

Pełne code review znajduje się tutaj:

**[Code review `legacy_promo.php`](./code-review.md)**

Problemy zostały tam podzielone zgodnie z wymaganiem zadania na:

- **krytyczne**,
- **istotne**,
- **kosmetyczne / utrzymaniowe**.

Przy każdym problemie krytycznym opisałem również jego realny skutek biznesowy.

Do najważniejszych zagrożeń należały m.in.:

- możliwość podwójnego obciążenia klienta,
- niewłaściwa idempotencja płatności,
- SQL Injection,
- sekrety zapisane bezpośrednio w kodzie,
- wyłączona weryfikacja TLS przy płatności,
- możliwość przekroczenia limitu wykorzystania promocji,
- możliwość wielokrotnego zastosowania promocji do tego samego koszyka,
- brak widocznej w dostarczonym kodzie autoryzacji części operacji.

---

# Część 1 – refaktoryzacja

Kod został rozdzielony według odpowiedzialności.

Uproszczony flow nakładania promocji:

```text
HTTP Request
      ↓
FormRequest
      ↓
ApplyPromotion
      ↓
Repositories
      ↓
PromotionValidator
      ↓
DiscountCalculator
      ↓
Database Transaction
      ↓
Result DTO
      ↓
JSON Response
```

Dzięki temu:

- kontroler nie zawiera reguł biznesowych,
- logika naliczania rabatu nie zależy od HTTP,
- szczegóły Eloquent pozostają w warstwie infrastrukturalnej,
- walidacja reguł promocji jest oddzielona od sposobu przechowywania danych,
- krytyczne elementy można testować bez przechodzenia przez cały stack HTTP.

---

## Pieniądze

W kodzie legacy pieniądze były reprezentowane za pomocą `float`.

W refaktorze kwoty przechowywane są jako liczby całkowite w najmniejszej jednostce waluty.

Przykład:

```text
10000 = 100,00 PLN
```

W kodzie aplikacji wykorzystywane jest `moneyphp/money`.

Pozwala to uniknąć problemów z dokładnością operacji zmiennoprzecinkowych.

Obecna reguła biznesowa zakłada:

```text
discount <= cart total
```

czyli wartość koszyka nigdy nie może spaść poniżej zera.

Rabat 100% jest obecnie dozwolony.

---

## Współbieżność promocji

Nałożenie promocji wykonywane jest w ramach transakcji bazodanowej.

Tam, gdzie konieczne jest zabezpieczenie danych przed równoczesną modyfikacją, wykorzystywana jest blokada bazodanowa.

Dodatkowo baza posiada unikalne ograniczenie:

```text
promo_code_id + cart_id
```

Dzięki temu ta sama promocja nie może zostać zapisana drugi raz dla tego samego koszyka również przy konkurencyjnych requestach.

---

## Statystyki promocji

Kod legacy określał sumę wartości koszyków jako:

```text
revenue
```

W refaktorze używam:

```text
cart_sum
```

Koszyk nie oznacza jeszcze zakończonego i opłaconego zamówienia, dlatego traktowanie jego wartości jako realnego przychodu byłoby mylące.

Statystyki zostały wydzielone jako osobny read/query flow.

Przykładowy endpoint:

```text
GET /api/dashboard/promotions/stats?code=PROMO
```

---

# Odnawianie subskrypcji

Proces odnowienia subskrypcji został wydzielony z HTTP i może być uruchamiany przez komendę:

```text
subscriptions:renew
```

Komenda deleguje właściwą logikę do warstwy aplikacyjnej.

Definicja Laravel Scheduler uruchamia ją cyklicznie.

W środowisku produkcyjnym scheduler powinien działać jako osobny proces / cron lub dedykowany kontener. Nie dodawałem osobnego procesu schedulera do lokalnego `docker-compose`, ponieważ nie jest on potrzebny do demonstracji rozwiązania i może zostać uruchomiony ręcznie.

---

## Idempotencja płatności

Jednym z najpoważniejszych problemów w kodzie legacy była możliwość dwukrotnego obciążenia klienta.

Każdy konkretny cykl odnowienia posiada teraz deterministyczny klucz idempotencji.

Przykład:

```text
subscription:15:renewal:2026-08-19T12:00:00
```

Identyfikator wskazuje konkretną subskrypcję oraz konkretny cykl odnowienia.

Ten sam klucz wykorzystywany jest:

- w lokalnym rekordzie odnowienia,
- przy requestcie do payment providera.

Chroni to m.in. przed scenariuszem:

```text
payment provider pobiera pieniądze
        ↓
aplikacja ulega awarii przed zapisem lokalnego stanu
        ↓
proces uruchamia się ponownie
        ↓
wysyłany jest ten sam idempotency key
```

Rozwiązanie zakłada, że payment provider wspiera idempotencję requestów.

Sama blokada lub transakcja w naszej bazie nie wystarczyłaby do rozwiązania problemu występującego pomiędzy dwoma niezależnymi systemami.

---

## Udane odnowienie

Po poprawnym obciążeniu klienta:

1. odnowienie oznaczane jest jako zakończone sukcesem,
2. tworzone jest odpowiadające mu zamówienie,
3. aktualizowana jest data kolejnego odnowienia.

Lokalne operacje wykonywane są w transakcji bazodanowej.

---

## Payment Gateway

Integracja z zewnętrznym systemem płatności została ukryta za kontraktem:

```text
PaymentGateway
```

Implementacja HTTP:

```text
PaymentGateway
       ↑
HttpPaymentGateway
```

Warstwa aplikacyjna nie musi dzięki temu znać sposobu komunikacji HTTP z konkretnym providerem.

Daje to również możliwość testowania procesu odnowienia przy użyciu fake'owego gateway bez wykonywania rzeczywistej płatności.

Sekrety integracji znajdują się w konfiguracji środowiskowej, a nie bezpośrednio w kodzie.

---

# Część 2 – wygasające karty płatnicze

Do subskrypcji została dodana informacja:

```text
card_expires_at
```

Aplikacja identyfikuje aktywne subskrypcje, dla których karta wygaśnie pomiędzy dniem bieżącym a kolejnymi 30 dniami.

Uproszczony flow:

```text
Subscription
      ↓
card_expires_at
      ↓
GetExpiringCards
      ↓
SubscriptionRepository
      ↓
active + expiration today...+30 days
      ↓
ExpiringCardDto
      ↓
REST API
```

Endpoint:

```text
GET /api/dashboard/subscriptions/expiring-cards
```

API zwraca dane potrzebne panelowi Customer Service lub systemowi marketing automation, np.:

```json
{
    "subscription_id": 10,
    "user_id": 15,
    "email": "customer@example.com",
    "card_expires_at": "2026-09-01",
    "days_until_expiration": 13
}
```

Token karty nie jest zwracany przez endpoint.

---

# Proponowany proces powiadamiania klienta

Zgodnie z wymaganiem zadania mechanizm wysyłania powiadomień został tylko zaprojektowany, a nie zaimplementowany.

W środowisku produkcyjnym uruchomiłbym raz dziennie proces wyszukujący subskrypcje z kartami zbliżającymi się do końca ważności.

Przykładowy flow:

```text
Scheduler
      ↓
FindExpiringCards
      ↓
CardExpiring event / notification job
      ↓
Queue
      ↓
Notification / Marketing integration
      ↓
Email / inny kanał kontaktu
```

Proces powinien:

1. znaleźć aktywne subskrypcje z wygasającymi kartami,
2. określić, czy dla danego klienta należy wygenerować przypomnienie,
3. wysyłać przypomnienia np. 30, 14 i 7 dni przed końcem ważności,
4. zapisywać historię wysłanych komunikatów,
5. zapobiegać wysłaniu tego samego przypomnienia więcej niż raz,
6. zakończyć przypominanie po aktualizacji karty,
7. ponawiać nieudane próby wysyłki,
8. monitorować błędy oraz skuteczność procesu.

Sam mechanizm identyfikacji wygasających kart powinien pozostać niezależny od kanału powiadomienia.

Dzięki temu z tych samych danych może korzystać:

```text
REST API dla Customer Service
Marketing Automation
Scheduler / Notification Service
```

---

# Decyzje projektowe

## PHP / Laravel

Wybrałem PHP i Laravel, ponieważ pozwalały skupić się na problemach biznesowych zadania zamiast na przygotowywaniu infrastruktury aplikacji od podstaw.

Laravel dostarcza również gotowe mechanizmy potrzebne w rozwiązaniu, m.in.:

- walidację requestów,
- transakcje,
- query builder / Eloquent,
- scheduler,
- HTTP client,
- migracje,
- testowanie API.

---

## Struktura aplikacji

W ramach zadania zastosowałem lekkie rozdzielenie:

```text
Application
Domain
Infrastructure
Presentation
```

Celem nie było odwzorowanie konkretnego wzorca architektonicznego w 100%.

Wzorce traktuję jako narzędzie, a nie jako cel sam w sobie.

Struktura kodu powinna przede wszystkim:

- ułatwiać wprowadzanie zmian,
- chronić ważne reguły biznesowe,
- pozwalać łatwo testować kod,
- być zrozumiała dla pozostałych członków zespołu,
- wspierać tempo rozwoju produktu.

Nie wprowadzałem dodatkowych abstrakcji tylko dlatego, że występują w konkretnym wzorcu architektonicznym.

---

## Kierunek przy dalszym rozwoju – modularny monolit

Nie znam pełnej architektury rzeczywistej aplikacji.

Przy rosnącym systemie w pierwszej kolejności rozwijałbym go jednak w kierunku **modularnego monolitu**, a nie mikroserwisów, dopóki nie pojawiłyby się konkretne problemy organizacyjne lub techniczne uzasadniające rozdzielenie procesów.

Przykładowy podział domeny:

```text
Promotion/
Subscription/
Payment/
Order/
Customer/
```

Każdy komponent mógłby posiadać własne:

```text
Application/
Domain/
Infrastructure/
Presentation/
```

Strukturę zastosowaną w zadaniu można więc potraktować jako uproszczony przykład sposobu organizacji pojedynczego większego komponentu.

Takie uporządkowanie daje dobry punkt wyjścia do dalszej rozbudowy, ale wraz ze zmianą skali lub kierunku produktu część warstw może zostać zarówno rozbudowana, jak i uproszczona.

---

## Repozytoria

Obecnie szczegóły Eloquent są ukryte głównie w repozytoriach infrastrukturalnych.

Nie dodawałem kontraktu do każdego repozytorium wyłącznie dla zachowania wzorca.

Tam, gdzie rzeczywiście istnieje zewnętrzna zależność i możliwość wymiany implementacji — jak `PaymentGateway` — kontrakt ma konkretną wartość.

Przy większej aplikacji lub silniejszej izolacji modułów rozważyłbym jednak:

```text
Application
    ↓
Repository Contract
    ↑
Eloquent Repository
```

Warstwa aplikacyjna nie wiedziałaby wtedy nawet, że dane przechowywane są za pomocą Eloquent.

Decyzja o wprowadzeniu takiego poziomu abstrakcji powinna jednak wynikać z realnej potrzeby, a nie tylko z chęci dodania kolejnego interfejsu.

---

## MySQL i SQLite

Środowisko uruchamiane przez Docker korzysta z MySQL.

Testy automatyczne wykorzystują SQLite in-memory, dzięki czemu są szybkie i izolowane.

Trade-offem jest możliwość wystąpienia różnic pomiędzy zachowaniem SQLite i MySQL przy bardziej skomplikowanych zapytaniach.

W większym systemie część testów integracyjnych uruchamiałbym również bezpośrednio na tej samej wersji MySQL, która działa produkcyjnie.

---

## Docker

Na potrzeby zadania kontener aplikacji wykorzystuje prosty serwer Laravel.

Pozwala to uruchomić projekt jedną komendą i ograniczyć liczbę elementów infrastrukturalnych potrzebnych do oceny zadania.

W środowisku produkcyjnym wykorzystałbym dedykowany sposób uruchamiania PHP, np. PHP-FPM + Nginx lub inne rozwiązanie dostosowane do infrastruktury projektu.

---

## CI

CI wykonuje tę samą kontrolę jakości, którą można uruchomić lokalnie:

```bash
make check
```

Dzięki temu nie istnieją dwa różne zestawy reguł dla środowiska lokalnego i CI.

Pipeline uruchamiany jest przy każdym:

```text
push
pull request
```

i obejmuje co najmniej wymagane przez zadanie:

```text
lint + tests
```

oraz dodatkowo:

```text
PHPStan
Rector dry-run
Composer Audit
```

---

# Otwarte pytania biznesowe

Podczas refaktoryzacji pojawiło się kilka decyzji, których nie chciałem arbitralnie zaszywać w kodzie bez znajomości rzeczywistych reguł produktu.

## Maksymalna wysokość rabatu

Obecna implementacja pozwala promocji obniżyć koszyk do:

```text
0 PLN
```

ale nigdy poniżej zera.

Przed wdrożeniem produkcyjnym chciałbym potwierdzić:

- czy promocja może pokryć 100% wartości zamówienia,
- czy powinien istnieć maksymalny procent rabatu,
- czy powinien istnieć maksymalny rabat kwotowy,
- czy minimalna wartość zamówienia po promocji powinna być większa od zera,
- czy ograniczenia powinny być definiowane osobno dla konkretnej promocji.

Takie ograniczenie powinno wynikać z decyzji biznesowej, a nie z arbitralnej wartości wpisanej przez programistę.

---

## Cykl odnowienia

W obecnej implementacji cykl odnowienia jest stałą regułą biznesową.

Jeżeli w rzeczywistym systemie istnieje więcej niż jeden plan subskrypcji, okres odnowienia powinien prawdopodobnie wynikać z konfiguracji konkretnego planu / subskrypcji zamiast z jednej globalnej wartości.

---

## Powiadomienia o karcie

Przykładowe progi:

```text
30 / 14 / 7 dni
```

są propozycją techniczną.

Rzeczywista częstotliwość i kanały komunikacji powinny wynikać z danych biznesowych, doświadczeń Customer Service oraz efektywności komunikacji z klientami.

---

# Elementy, które rozważyłbym przy dalszym rozwoju

Zakres zadania celowo ograniczyłem do elementów potrzebnych do rozwiązania opisanych problemów.

Przed wdrożeniem podobnego rozwiązania do pełnego środowiska produkcyjnego rozważyłbym dodatkowo:

- **authentication** endpointów wewnętrznych, np. Laravel Sanctum,
- **authorization**, w szczególności sprawdzanie właściciela koszyka,
- role i permission dla endpointów Customer Service / statystyk,
- rate limiting dla API,
- dedykowane wyjątki biznesowe i ich mapowanie na odpowiednie odpowiedzi HTTP,
- structured logging,
- audyt istotnych operacji,
- monitoring błędów,
- retry i reconciliation dla płatności,
- kolejki dla procesów asynchronicznych,
- monitoring schedulera,
- historię i deduplikację powiadomień,
- alertowanie przy problemach z procesem odnowień,
- testy integracyjne wykorzystujące MySQL,
- pagination dla endpointów zwracających większe zbiory danych.

Wraz ze wzrostem liczby endpointów uporządkowałbym również routing per moduł zamiast rozbudowywać jeden plik poprzez kolejne zagnieżdżone grupy i prefiksy.

---

# Struktura projektu

Uproszczona struktura:

```text
app/
├── Application/
│   ├── Action/
│   ├── Contract/
│   ├── DTO/
│   └── Query/
│
├── Domain/
│   ├── Calculator/
│   ├── DTO/
│   ├── Enum/
│   └── Validator/
│
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Cast/
│   │   └── Eloquent/
│   │       ├── Entity/
│   │       └── Repository/
│   └── Provider/
│
├── Payment/
│
├── Presentation/
│   └── Http/
│       ├── Controller/
│       ├── Request/
│       └── routes.php
│
└── Resource/
    └── config/
```

Testy:

```text
app/Test/
├── Feature/
└── Unit/
```

---

# DevOps

Projekt zawiera:

```text
Dockerfile
docker-compose.yml
docker/entrypoint.sh
.github/workflows/ci.yml
Makefile
```

Środowisko Docker:

```text
PHP 8.4 / Laravel
        ↓
MySQL 8.4
```

Healthcheck MySQL powoduje, że aplikacja uruchamia migracje dopiero po gotowości bazy.

Dzięki temu świeże repozytorium można uruchomić jednym poleceniem:

```bash
docker compose up --build
```

---

# Świadomie pozostawione poza implementacją

Nie implementowałem:

- pełnego systemu wysyłania powiadomień o wygasającej karcie — zgodnie z zadaniem został tylko zaprojektowany,
- systemu authentication / authorization dashboardu,
- systemu ról Customer Service,
- osobnej infrastruktury kolejkowej,
- produkcyjnego procesu schedulera,
- pełnego observability stack,
- kontraktów dla wszystkich repozytoriów,
- maksymalnej biznesowej wysokości rabatu poza ograniczeniem go do wartości koszyka.

Są to elementy, które można dodać w zależności od rzeczywistego kontekstu aplikacji i planów jej dalszego rozwoju.

Celowo nie chciałem rozszerzać rozwiązania o elementy, które nie były potrzebne do zweryfikowania głównych problemów zadania.

---

# Podejście do architektury

Lubię utrzymywać porządek w kodzie, ponieważ dobrze określone odpowiedzialności ułatwiają późniejsze zmiany i rozbudowę systemu.

Nie traktuję jednak architektury ani wzorców jako zbioru reguł, które trzeba odwzorować w 100%.

Dla mnie:

```text
potrzeba biznesowa
        ↓
czytelny model problemu
        ↓
najprostsza architektura, która dobrze go obsłuży
```

jest lepszym kierunkiem niż:

```text
wzorzec
        ↓
kolejne abstrakcje
        ↓
dopasowanie problemu do architektury
```

Jeżeli system ma się szybko rozwijać, uporządkowana struktura daje dobry punkt startowy do zmian.

Jeżeli część tej struktury okaże się zbędna — powinna móc zostać uproszczona.

---

# Wykorzystanie AI

Podczas realizacji zadania korzystałem głównie z:

- **ChatGPT**,
- pomocniczo **Gemini**.

Narzędzia AI wykorzystywałem m.in. do:

- konsultowania potencjalnych problemów w kodzie legacy,
- dyskusji nad wariantami architektury,
- przygotowania części boilerplate'u,
- wsparcia przy przygotowywaniu testów,
- analizy komunikatów PHPStan / Larastan,
- konfiguracji Docker i CI,
- przeglądu możliwych edge case'ów.

Sugestii AI nie traktowałem jako gotowego rozwiązania.

Kod był przeze mnie ręcznie analizowany, dostosowywany do przyjętych konwencji projektu oraz uruchamiany i weryfikowany.

Do ręcznej weryfikacji wykorzystałem m.in.:

```bash
php artisan test
php artisan route:list
php artisan schedule:list
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/rector process --dry-run
composer audit
docker compose up --build
```

Dodatkowo ręcznie analizowałem szczególnie:

- transakcje i blokady bazodanowe,
- zachowanie promocji przy wartościach granicznych,
- sposób generowania klucza idempotencji,
- scenariusz awarii pomiędzy payment providerem a zapisem lokalnego stanu,
- dane wystawiane przez endpoint wygasających kart.

---

# Podsumowanie

W rozwiązaniu priorytetem były problemy mające bezpośredni wpływ na klientów i biznes:

```text
podwójne obciążenie klienta
bezpieczeństwo danych
niekontrolowane wykorzystanie promocji
spójność danych
wygasające karty
```

Dopiero później skupiłem się na uporządkowaniu struktury, automatycznych testach, analizie statycznej i automatyzacji środowiska developerskiego.

Celem nie było stworzenie maksymalnie rozbudowanej architektury, tylko rozwiązania, które jest bezpieczniejsze, czytelne, testowalne i daje sensowną bazę do dalszego rozwoju.
