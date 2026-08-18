# Code Review — `legacy_promo.php`

## Cel review

Poniżej opisuję problemy, które zauważyłem w dostarczonym pliku `legacy_promo.php`.

Starałem się priorytetyzować uwagi według realnego ryzyka dla biznesu i użytkowników, a nie według samego stylu kodu.

Podział:
- **Krytyczne** — mogą prowadzić do strat finansowych, naruszenia bezpieczeństwa albo poważnej niespójności danych.
- **Istotne** — zwiększają ryzyko błędów, utrudniają rozwój i mogą prowadzić do problemów produkcyjnych.
- **Kosmetyczne / utrzymaniowe** — nie muszą od razu powodować błędu, ale utrudniają dalszy rozwój i utrzymanie systemu.

---

# Krytyczne

## CR-01 — Możliwe podwójne obciążenie klienta przy odnowieniu subskrypcji

### Gdzie

W części `renew_subscriptions` pobierane są wszystkie aktywne subskrypcje, których termin odnowienia już minął:

```php
SELECT * FROM subscriptions
WHERE next_renewal <= NOW()
AND status = 'active'
```

Następnie wykonywana jest płatność, a dopiero po jej powodzeniu aktualizowany jest `next_renewal`.

### Problem

Jeżeli dwa procesy uruchomią się prawie jednocześnie, oba mogą pobrać tę samą subskrypcję zanim którykolwiek z nich zmieni jej stan.

Przykład:

1. proces A pobiera subskrypcję nr 10,
2. proces B również pobiera subskrypcję nr 10,
3. proces A wykonuje płatność,
4. proces B również wykonuje płatność,
5. oba procesy aktualizują `next_renewal`.

Drugi możliwy scenariusz:

1. payment provider zwraca sukces,
2. aplikacja przestaje działać przed aktualizacją bazy,
3. `next_renewal` nadal wskazuje zaległe odnowienie,
4. cron uruchamia się ponownie za godzinę,
5. klient może zostać obciążony ponownie.

W kodzie znajduje się też komentarz:

```php
// FIXME: czasami klienci sa obciazani 2x, do sprawdzenia kiedys
```

### Skutek biznesowy

Klient może zostać obciążony dwa razy za jedno odnowienie subskrypcji, co oznacza reklamacje, zwroty i utratę zaufania.

### Jak bym to poprawił

Wprowadziłbym trwały zapis konkretnej próby / cyklu odnowienia oraz poprawnie zaprojektowaną idempotencję. Konkretna płatność powinna mieć własny unikalny identyfikator, a system powinien umieć rozpoznać, że dany cykl odnowienia został już obsłużony.

---

## CR-02 — `request_id` nie identyfikuje konkretnego odnowienia

### Gdzie

```php
'request_id' => md5($sub['id'])
```

### Problem

`request_id` jest zawsze taki sam dla danej subskrypcji.

Jeżeli subskrypcja nr `15` odnawia się co tydzień, każde kolejne prawidłowe odnowienie otrzyma taki sam `request_id`.

Jeżeli payment provider używa tego pola jako klucza idempotencji, kolejne prawidłowe odnowienie może zostać potraktowane jak duplikat poprzedniej płatności.

### Skutek biznesowy

Prawidłowe odnowienia mogą zostać odrzucone albo system może zachowywać się inaczej niż zakładamy w zależności od implementacji providera.

### Jak bym to poprawił

Identyfikator powinien wskazywać konkretny cykl odnowienia, np. na podstawie:

```text
subscription_id + renewal_date
```

i być zapisany po stronie aplikacji.

---

## CR-03 — SQL Injection

### Gdzie

Dane pochodzące z requestu są bezpośrednio doklejane do zapytań SQL.

Przykład:

```php
$code = $_GET['code'];

$q = mysqli_query(
    $conn,
    "SELECT * FROM promo_codes WHERE code = '$code'"
);
```

Podobny problem występuje m.in. przy:

```php
SELECT * FROM carts WHERE id = $cart_id
```

oraz:

```sql
WHERE p.code LIKE '%$code%'
```

### Problem

Użytkownik kontroluje część zapytania SQL.

### Skutek biznesowy

W najgorszym przypadku atakujący może uzyskać nieautoryzowany dostęp do danych albo je zmodyfikować.

### Jak bym to poprawił

Użyłbym zapytań parametryzowanych. W refaktorze opartym o Laravel skorzystałbym z Query Buildera lub Eloquent.

---

## CR-04 — Dane dostępowe i klucz payment providera znajdują się w kodzie

### Gdzie

```php
$db_user = "root";
$db_pass = "Miesna2021!";
```

oraz:

```php
'Authorization: Bearer sk_live_51Hb9x2Fj3kD8s7Gh2'
```

### Problem

Sekrety produkcyjne znajdują się bezpośrednio w repozytorium.

Dodatkowo połączenie z bazą wykonywane jest użytkownikiem `root`, który prawdopodobnie posiada znacznie większe uprawnienia niż potrzebuje aplikacja.

### Skutek biznesowy

Wyciek repozytorium może oznaczać dostęp do produkcyjnej bazy danych lub konta operatora płatności.

### Jak bym to poprawił

- sekrety przeniósłbym do zmiennych środowiskowych / secret managera,
- ujawnione dane dostępowe powinny zostać zrotowane,
- aplikacja powinna korzystać z użytkownika DB posiadającego tylko niezbędne uprawnienia.

---

## CR-05 — Wyłączona weryfikacja certyfikatu TLS przy płatności

### Gdzie

```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```

### Problem

Aplikacja nie weryfikuje poprawnie certyfikatu serwera, z którym nawiązuje szyfrowane połączenie.

Jest to szczególnie niebezpieczne w miejscu odpowiedzialnym za płatności.

### Skutek biznesowy

Komunikacja z operatorem płatności może zostać przechwycona lub skierowana do niewłaściwego serwera.

### Jak bym to poprawił

Weryfikacja certyfikatu powinna być włączona i korzystać z poprawnie skonfigurowanego środowiska CA.

---

## CR-06 — Możliwe przekroczenie limitu użyć kodu rabatowego

### Gdzie

Najpierw sprawdzana jest liczba wykorzystań:

```php
SELECT COUNT(*) as cnt
FROM promo_usages
WHERE promo_id = ...
```

następnie:

```php
if ($usage['cnt'] >= $promo['max_usages']) {
    ...
}
```

a wykorzystanie zapisywane jest dopiero później.

### Problem

Dwa równoczesne requesty mogą zobaczyć ten sam stan.

Przykład dla `max_usages = 100`:

1. request A widzi 99 użyć,
2. request B widzi 99 użyć,
3. A akceptuje kod,
4. B również akceptuje kod,
5. ostatecznie kod ma 101 użyć.

### Skutek biznesowy

Promocja może zostać wykorzystana więcej razy niż zakładał biznes, co generuje nieplanowane rabaty.

### Jak bym to poprawił

Sprawdzenie limitu i zapis wykorzystania powinny być wykonane atomowo, np. z użyciem transakcji i odpowiedniej blokady / innego mechanizmu zabezpieczającego współbieżność.

---

## CR-07 — Ten sam kod rabatowy można prawdopodobnie zastosować kilka razy do tego samego koszyka

### Gdzie

Kod bierze aktualną wartość koszyka:

```php
$total = floatval($cart['total']);
```

nalicza od niej rabat i zapisuje nową wartość:

```php
UPDATE carts
SET total = $new_total, promo_code = '$code'
WHERE id = $cart_id
```

### Problem

Nie ma sprawdzenia, czy promocja została już zastosowana do tego koszyka.

Przykład dla promocji 10%:

```text
100 zł
→ 90 zł
→ 81 zł
→ 72,90 zł
```

Kolejne wywołania endpointu mogą ponownie obniżać aktualną wartość koszyka.

### Skutek biznesowy

Klient może otrzymać znacznie większy rabat niż zakłada promocja.

### Jak bym to poprawił

Przed zastosowaniem promocji sprawdziłbym, czy dany koszyk już z niej skorzystał. Dodatkowo zabezpieczyłbym tę regułę na poziomie bazy danych.

---

## CR-08 — W pokazanym kodzie nie ma autoryzacji operacji

### Gdzie

Akcja wybierana jest bezpośrednio z parametru:

```php
$action = $_GET['action'];
```

Możliwe są m.in.:

```text
?action=renew_subscriptions
?action=get_promo_stats
```

### Problem

W samym pokazanym pliku nie ma mechanizmu uwierzytelnienia ani autoryzacji.

Nie wiem, czy taki mechanizm istnieje przed wejściem do tego skryptu, dlatego traktuję to jako ryzyko wynikające z dostarczonego kodu.

### Skutek biznesowy

Jeżeli endpoint jest publicznie dostępny bez dodatkowej ochrony, osoba nieuprawniona może uruchamiać proces odnowień lub odczytywać dane marketingowe.

### Jak bym to poprawił

- operacje użytkownika powinny wymagać autoryzacji,
- wewnętrzny proces odnowień nie powinien być publiczną akcją GET,
- cron/scheduler powinien uruchamiać dedykowany command/job.

---

# Istotne

## IMP-01 — Kwoty pieniężne są liczone jako `float`

### Gdzie

```php
$total = floatval($cart['total']);
```

oraz:

```php
$discount = $total * $promo['value'] / 100;
```

### Problem

`float` nie gwarantuje dokładnej reprezentacji wartości dziesiętnych.

Przy pieniądzach mogą więc pojawić się błędy zaokrągleń.

### Jak bym to poprawił

Kwoty przechowywałbym jako liczby całkowite w najmniejszej jednostce waluty (grosze) albo użył obiektu / typu przeznaczonego do operacji pieniężnych. Wykorzystałbym tutaj bibliotekę Money.

---

## IMP-02 — Brak transakcji przy zastosowaniu kodu rabatowego

### Gdzie

Najpierw aktualizowany jest koszyk:

```php
UPDATE carts ...
```

a później zapisywane użycie promocji:

```php
INSERT INTO promo_usages ...
```

### Problem

Jeżeli `UPDATE` się powiedzie, ale `INSERT` nie, klient otrzyma rabat, ale wykorzystanie kodu nie zostanie zapisane.

Baza znajdzie się wtedy w niespójnym stanie.

### Jak bym to poprawił

Obie operacje powinny zostać wykonane w jednej transakcji bazodanowej.

---

## IMP-03 — Brak transakcji po udanej płatności

### Gdzie

Po sukcesie payment providera wykonywane są osobno:

```php
UPDATE subscriptions ...
```

oraz:

```php
INSERT INTO orders ...
```

### Problem

Pierwsza operacja może się udać, a druga zakończyć błędem.

Wtedy subskrypcja zostanie przesunięta na kolejny termin, ale nie powstanie odpowiadające jej zamówienie.

### Jak bym to poprawił

Lokalne zmiany w bazie objąłbym transakcją.

Sama transakcja DB nie rozwiązuje jednak problemu atomowości pomiędzy naszą bazą a zewnętrznym payment providerem — dlatego dodatkowo potrzebny jest trwały model próby odnowienia / płatności i idempotencja.

---

## IMP-04 — Brak obsługi błędów połączenia z payment providerem

### Gdzie

```php
$result = curl_exec($ch);
$response = json_decode($result, true);

if ($response['status'] == 'ok') {
```

### Problem

Kod zakłada, że:

- połączenie się powiedzie,
- odpowiedź będzie poprawnym JSON-em,
- będzie zawierać pole `status`.

Nie są sprawdzane np.:

- `curl_error`,
- kod HTTP,
- timeout,
- niepoprawna odpowiedź,
- brak odpowiedzi.

### Jak bym to poprawił

Obsłużyłbym oddzielnie błędy transportowe, odpowiedzi HTTP i błędy biznesowe operatora płatności oraz dodał logowanie i kontrolowany retry.

---

## IMP-05 — Brak jawnie ustawionych timeoutów dla requestu płatniczego

### Problem

Wywołanie zewnętrznego systemu może trwać zbyt długo i blokować cały proces odnowień.

### Jak bym to poprawił

Ustawiłbym rozsądny timeout połączenia i całego requestu oraz odpowiednią strategię retry.

---

## IMP-06 — Brak walidacji danych wejściowych

### Gdzie

Dane pobierane są bezpośrednio z `$_GET`:

```php
$code = $_GET['code'];
$cart_id = $_GET['cart_id'];
$user_email = $_GET['email'];
```

### Problem

Nie są sprawdzane m.in.:

- obecność parametrów,
- typ `cart_id`,
- poprawność adresu email,
- długość / format kodu.

### Jak bym to poprawił

W Laravelu użyłbym dedykowanego Form Request / walidacji requestu.

---

## IMP-07 — Brak obsługi nieistniejącego koszyka lub użytkownika

### Gdzie

```php
$cart = mysqli_fetch_assoc($q3);
$total = floatval($cart['total']);
```

oraz:

```php
$user = mysqli_fetch_assoc($user_q);
```

### Problem

Kod zakłada, że rekord zawsze istnieje.

Jeżeli zapytanie nie zwróci wyniku, dalsza część logiki może działać na `null` i wygenerować błąd.

### Jak bym to poprawił

Brak danych powinien być obsłużony jawnie i zwrócić kontrolowany błąd.

---

## IMP-08 — Tymczasowa promocja z 2023 roku nadal działa

### Gdzie

```php
// promocja urodzinowa 2023 - do usuniecia po urodzinach
if (date('m') == '09') {
    $new_total = $new_total * 0.95;
}
```

### Problem

Komentarz wskazuje, że mechanizm miał być tymczasowy.

W obecnej formie w każdym wrześniu w tym flow naliczany jest dodatkowy rabat 5%.

### Jak bym to poprawił

Reguła promocji powinna mieć konfigurację, okres obowiązywania i jasne warunki naliczania zamiast pozostawać jako specjalny `if` w kodzie.

---

## IMP-09 — `GET` jest używany do operacji zmieniających stan

### Gdzie

Przykładowo:

```text
promo.php?action=apply_promo
promo.php?action=renew_subscriptions
```

### Problem

`GET` powinien służyć do pobierania danych, a nie wykonywania operacji zmieniających stan.

### Jak bym to poprawił

Np.:

```text
POST /api/carts/{cart}/promotion
```

Odnowienia subskrypcji uruchamiałbym przez scheduler / command / job, a nie publiczny endpoint.

---

## IMP-10 — Mail wysyłany synchronicznie, a błędy są ukrywane

### Gdzie

```php
@mail(...)
```

### Problem

`@` ukrywa błędy.

Sama wysyłka wiadomości jest też wykonywana bezpośrednio w procesie odnowienia subskrypcji.

### Jak bym to poprawił

Po poprawnym odnowieniu opublikowałbym event / job i wysłał wiadomość asynchronicznie przez kolejkę. Błędy wysyłki powinny być logowane i możliwe do ponowienia.

---

## IMP-11 — N+1 przy pobieraniu użytkowników subskrypcji

### Gdzie

Najpierw pobierane są subskrypcje, a następnie dla każdej wykonywane jest osobne zapytanie:

```php
while ($sub = mysqli_fetch_assoc($q)) {
    $user_q = mysqli_query(
        $conn,
        "SELECT * FROM users WHERE id = " . $sub['user_id']
    );
}
```

### Problem

Dla dużej liczby subskrypcji generowana jest duża liczba dodatkowych zapytań.

### Jak bym to poprawił

Pobrałbym potrzebne dane jednym zapytaniem / relacją lub przetwarzał dane porcjami.

---

## IMP-12 — Statystyka `revenue` jest liczona na podstawie koszyków

### Gdzie

```sql
SUM(c.total) as revenue
```

Dane pochodzą z:

```sql
LEFT JOIN carts c ON c.id = u.cart_id
```

### Problem

Koszyk nie musi oznaczać zrealizowanego i opłaconego zamówienia.

Klient może użyć kodu, a później porzucić koszyk.

Dashboard marketingowy może więc traktować wartość porzuconych koszyków jako przychód.

### Jak bym to poprawił

Przychód liczyłbym na podstawie opłaconych / zrealizowanych zamówień zgodnie z biznesową definicją revenue w systemie.

---

## IMP-13 — Możliwy XSS przy generowaniu HTML

### Gdzie

Przykładowo:

```php
echo "<div style='color:red'>Kod " . $_GET['code'] . " nie istnieje!</div>";
```

oraz:

```php
echo "<tr><td>" . $row['code'] . ...
```

### Problem

Dane są umieszczane w HTML bez escapowania.

### Jak bym to poprawił

Warstwa API powinna zwracać dane, a nie budować HTML. Jeżeli dane są renderowane w HTML, wartości kontrolowane przez użytkownika powinny zostać odpowiednio escapowane.

---

## IMP-14 — Błędy połączenia z bazą i zapytań są ukrywane lub ignorowane

### Gdzie

```php
$conn = @mysqli_connect(...)
```

oraz brak sprawdzania wyniku większości `mysqli_query`.

### Problem

System może kontynuować wykonywanie kodu mimo problemów z bazą, a operator nie dostaje czytelnej informacji, co się wydarzyło.

### Jak bym to poprawił

Błędy powinny być obsługiwane jawnie, logowane i kończyć dany use case w kontrolowany sposób.

---

# Kosmetyczne / utrzymaniowe

## MIN-01 — Jeden plik odpowiada za kilka niezależnych procesów

`legacy_promo.php` obsługuje jednocześnie:

- naliczanie promocji,
- odnowienia subskrypcji,
- statystyki marketingowe,
- komunikację HTTP,
- SQL,
- komunikację z payment providerem,
- wysyłkę maili,
- renderowanie HTML.

Utrudnia to testowanie i bezpieczne wprowadzanie zmian.

W refaktorze rozdzieliłbym te odpowiedzialności na osobne use case’y / serwisy.

---

## MIN-02 — Logika biznesowa jest wymieszana z transportem i infrastrukturą

Przykładowo w jednej ścieżce znajdują się razem:

```text
$_GET
→ SQL
→ obliczenie rabatu
→ UPDATE
→ INSERT
→ echo HTML
```

Chciałbym rozdzielić:

- obsługę requestu,
- logikę biznesową,
- zapis danych,
- prezentację odpowiedzi.

Dzięki temu reguły naliczania promocji będzie można testować bez bazy i HTTP.

---

## MIN-03 — Magiczne wartości i reguły ukryte w kodzie

Przykłady:

```php
INTERVAL 7 DAY
```

```php
date('m') == '09'
```

```php
$new_total * 0.95
```

Nie wiadomo od razu, z jakiej reguły biznesowej wynikają te wartości.

Warto nadać im nazwę albo przenieść do konfiguracji / modelu domenowego.

---

## MIN-04 — Brak typowania i jasno określonych kontraktów

Kod proceduralny operuje na tablicach zwracanych z bazy.

Nie ma jasno określonych typów wejścia i wyjścia ani obiektów reprezentujących podstawowe pojęcia, np. promocję czy wynik naliczenia rabatu.

W refaktorze wykorzystałbym typowanie PHP 8 oraz niewielkie klasy / DTO tam, gdzie poprawiają czytelność.

---

# Najważniejsze ryzyka w skrócie

Gdybym miał przed rozpoczęciem refaktoru wybrać najważniejsze rzeczy do zabezpieczenia, byłyby to:

1. **Idempotencja odnowień subskrypcji i ryzyko podwójnego obciążenia klienta.**
2. **SQL Injection i sekrety znajdujące się w kodzie.**
3. **Wyłączona weryfikacja TLS przy komunikacji z payment providerem.**
4. **Współbieżność przy limicie użyć kodu rabatowego.**
5. **Możliwość wielokrotnego naliczania promocji na ten sam koszyk.**
6. **Spójność danych podczas aktualizacji koszyka, użycia promocji i odnowienia subskrypcji.**

---

# Założenia / pytania biznesowe przed refaktorem

Przed podjęciem części decyzji chciałbym doprecyzować:

1. Czy jeden koszyk może mieć tylko jeden kod promocyjny, czy promocje mogą być łączone?
2. Czy `max_usages` jest limitem globalnym dla kodu, czy istnieje również limit na użytkownika / koszyk?
3. Czy `promo_usages` ma reprezentować samo użycie kodu w koszyku, czy dopiero wykorzystanie go w zakończonym zamówieniu?
4. Jaka jest biznesowa definicja `revenue` w statystykach promocji — wartość koszyków, złożonych zamówień czy opłaconych zamówień?
5. Jak payment provider interpretuje `request_id` i czy wspiera natywny idempotency key?
