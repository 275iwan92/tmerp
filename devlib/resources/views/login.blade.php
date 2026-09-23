@extends('layout', ['title' => 'Masuk'])
@section('body')
    <x-dl::login :action="route('login.submit')" :model="$model" :error="$error" :username="$username" />
@endsection
