@extends('layouts.dashboard')

@section('title', 'Profil Saya')
@section('subtitle', 'Dashboard Admin')

@php
    $user = auth()->user();
    $profileFields = [
        ['name' => 'name', 'label' => 'Nama Lengkap', 'value' => $user->name],
        ['name' => 'email', 'label' => 'Email', 'value' => $user->email],
        ['label' => 'Role', 'value' => 'Admin', 'locked' => true],
    ];
@endphp

@section('content')
    <x-ui.profile :user="$user" :fields="$profileFields" :password="$user->visiblePassword()" />
@endsection