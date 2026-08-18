<?php
// ============================================================
// promo.php - obsluga kodow rabatowych + odnowienia subskrypcji
// Uproszczony kod przygotowany na potrzeby zadania rekrutacyjnego,
// implementujacy funkcje zblizone do tych, ktore sa obslugiwane
// przez systemy Miesnej Paczki.
// Wywolanie: promo.php?action=apply_promo | renew_subscriptions
// ============================================================

$db_host = "prod-db-01.miesnapaczka.internal";
$db_user = "root";
$db_pass = "Miesna2021!";
$conn = @mysqli_connect($db_host, $db_user, $db_pass, "shop");

$action = $_GET['action'];

if ($action == 'apply_promo') {

    $code = $_GET['code'];
    $cart_id = $_GET['cart_id'];
    $user_email = $_GET['email'];

    $q = mysqli_query($conn, "SELECT * FROM promo_codes WHERE code = '$code'");
    $promo = mysqli_fetch_assoc($q);

    if (!$promo) {
        echo "<div style='color:red'>Kod " . $_GET['code'] . " nie istnieje!</div>";
        exit;
    }

    // sprawdzenie daty waznosci
    $today = date('Y-m-d');
    if ($promo['expires_at'] < $today) {
        echo "<div style='color:red'>Kod wygasl!</div>";
        exit;
    }

    // sprawdzenie limitu uzyc
    $q2 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM promo_usages WHERE promo_id = " . $promo['id']);
    $usage = mysqli_fetch_assoc($q2);
    if ($usage['cnt'] >= $promo['max_usages']) {
        echo "<div style='color:red'>Limit kodu wyczerpany!</div>";
        exit;
    }

    $q3 = mysqli_query($conn, "SELECT * FROM carts WHERE id = $cart_id");
    $cart = mysqli_fetch_assoc($q3);

    $total = floatval($cart['total']);

    // naliczenie rabatu
    if ($promo['type'] == 'percent') {
        $discount = $total * $promo['value'] / 100;
    } else if ($promo['type'] == 'amount') {
        $discount = $promo['value'];
    } else {
        $discount = 0;
    }

    // rabat kwotowy nie moze byc wiekszy niz koszyk
    if ($discount > $total) {
        $discount = $total;
    }

    $new_total = $total - $discount;

    // promocja urodzinowa 2023 - do usuniecia po urodzinach
    if (date('m') == '09') {
        $new_total = $new_total * 0.95;
    }

    mysqli_query($conn, "UPDATE carts SET total = $new_total, promo_code = '$code' WHERE id = $cart_id");
    mysqli_query($conn, "INSERT INTO promo_usages (promo_id, cart_id, email, used_at) VALUES (" . $promo['id'] . ", $cart_id, '$user_email', NOW())");

    echo "<div style='color:green'>Rabat naliczony! Nowa kwota: " . $new_total . " zl</div>";

} else if ($action == 'renew_subscriptions') {

    // CRON: odnawianie subskrypcji - uruchamiane co godzine
    // FIXME: czasami klienci sa obciazani 2x, do sprawdzenia kiedys

    $q = mysqli_query($conn, "SELECT * FROM subscriptions WHERE next_renewal <= NOW() AND status = 'active'");

    while ($sub = mysqli_fetch_assoc($q)) {

        $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = " . $sub['user_id']);
        $user = mysqli_fetch_assoc($user_q);

        // pobranie platnosci
        $payload = array(
            'card_token' => $user['card_token'],
            'amount' => floatval($sub['price']) * 100,
            'currency' => 'PLN',
            'request_id' => md5($sub['id'])
        );

        $ch = curl_init("https://api.payments-provider.example/charge");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer sk_live_51Hb9x2Fj3kD8s7Gh2'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);

        $response = json_decode($result, true);

        if ($response['status'] == 'ok') {
            mysqli_query($conn, "UPDATE subscriptions SET next_renewal = DATE_ADD(next_renewal, INTERVAL 7 DAY) WHERE id = " . $sub['id']);
            mysqli_query($conn, "INSERT INTO orders (user_id, subscription_id, total, created_at) VALUES (" . $sub['user_id'] . ", " . $sub['id'] . ", " . $sub['price'] . ", NOW())");

            // wyslanie maila z potwierdzeniem
            @mail($user['email'], "Twoja Miesna Paczka jest w drodze!", "Dziekujemy za odnowienie subskrypcji. Kwota: " . $sub['price'] . " zl");
        } else {
            // platnosc sie nie udala - sprobujemy za godzine
        }
    }

    echo "OK";

} else if ($action == 'get_promo_stats') {

    // uzywane przez dashboard marketingu
    $code = $_GET['code'];
    $q = mysqli_query($conn, "SELECT p.code, p.value, p.type, COUNT(u.id) as usages, SUM(c.total) as revenue
        FROM promo_codes p
        LEFT JOIN promo_usages u ON u.promo_id = p.id
        LEFT JOIN carts c ON c.id = u.cart_id
        WHERE p.code LIKE '%$code%'
        GROUP BY p.id");

    echo "<table border=1>";
    echo "<tr><th>Kod</th><th>Uzycia</th><th>Przychod</th></tr>";
    while ($row = mysqli_fetch_assoc($q)) {
        echo "<tr><td>" . $row['code'] . "</td><td>" . $row['usages'] . "</td><td>" . $row['revenue'] . "</td></tr>";
    }
    echo "</table>";
}

mysqli_close($conn);
?>
