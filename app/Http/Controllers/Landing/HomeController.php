<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('landing.index', [
            'totalSiswa' => User::query()
                ->where('role', 'siswa')
                ->count(),
            'guruAktif' => User::query()
                ->where('role', 'guru')
                ->where('status', User::STATUS_AKTIF)
                ->count(),
        ]);
    }
}
