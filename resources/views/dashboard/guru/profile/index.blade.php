@extends('layouts.dashboard')

@section('title', 'Profil Saya')
@section('subtitle', 'Dashboard Guru')

@php
    $user = auth()->user();
    $profileFields = [
        ['name' => 'name', 'label' => 'Nama Lengkap', 'value' => $user->name],
        ['name' => 'email', 'label' => 'Email', 'value' => $user->email],
        ['name' => 'nip', 'label' => 'NIP', 'value' => $user->nip],
        ['name' => 'mata_pelajaran', 'label' => 'Mata Pelajaran', 'value' => $user->mata_pelajaran],
        ['label' => 'Role', 'value' => 'Guru', 'locked' => true],
    ];
@endphp

@section('content')
    <x-ui.profile :user="$user" :fields="$profileFields" :password="$user->visiblePassword()" />
@endsection