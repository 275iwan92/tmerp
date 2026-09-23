<x-dl::frame bg="login" bg-order="sequence" bg-interval="8" box="center" box-style="glass" color="light" widgets="brand@top-left, weather@top-right, greeting@bottom-left, quote@bottom-left, date@bottom-right">
    <x-dl::login-box show="support, lang, brand, username, password, button" button="Masuk Aplikasi" :action="$action" :error="$error" :username="$username" />
</x-dl::frame>
