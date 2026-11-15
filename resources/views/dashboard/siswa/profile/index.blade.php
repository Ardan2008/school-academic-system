@extends('layouts.dashboard')

@section('title', 'Profil Saya')
@section('subtitle', 'Dashboard Siswa')

@php
    $user = auth()->user();
    $profileFields = [
        ['name' => 'name', 'label' => 'Nama Lengkap', 'value' => $user->name],
        ['name' => 'email', 'label' => 'Email', 'value' => $user->email],
        ['name' => 'nis', 'label' => 'NIS', 'value' => $user->nis, 'locked' => true],
        ['name' => 'kelas', 'label' => 'Kelas', 'value' => $user->kelas, 'locked' => true],
        ['name' => 'jurusan', 'label' => 'Jurusan', 'value' => $user->jurusan, 'locked' => true],
        ['label' => 'Role', 'value' => 'Siswa', 'locked' => true],
    ];
@endphp

@section('content')
    <x-ui.profile :user="$user" :fields="$profileFields" :password="$user->visiblePassword()" />
@endsection