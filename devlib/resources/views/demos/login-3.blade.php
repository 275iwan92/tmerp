<x-dl::frame bg="login" box="left" color="navy" widgets="greeting@top-right, clock@top-right, weather@top-right, announce@bottom-right, quote@bottom-right" quote-interval="10">
    <x-dl::login-box show="lang, brand, title, email, password, remember, forgot, button" :action="$action" :error="$error" :username="$username" />
</x-dl::frame>
