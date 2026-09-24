@extends('layout', ['title' => 'Masuk'])
@section('body')
<x-dl::frame bg="login" bg-order="random" box="right" color="light" resizable widgets="brand@top-left, clock@top-right, quote@bottom-left">
    <x-dl::login-box show="support, lang, title, username, password, forgot, button, register" :action="route('login.submit')" :error="$error" :username="$username" />
</x-dl::frame>
@endsection
