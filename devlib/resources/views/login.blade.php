@extends('layout', ['title' => 'Masuk'])
@section('body')
    @include('demos.login-'.$variant, ['action' => route('login.submit'), 'error' => $error, 'username' => $username])
@endsection
