@extends('layouts.auth')

@section('title', 'Dashboard | KIP-K UNAIR')
@section('description', 'Dashboard awal portal KIP-K Universitas Airlangga')

@section('content')
<main class="min-h-screen bg-background px-6 py-12">
    <div class="mx-auto max-w-3xl rounded-3xl bg-surface p-8 shadow-xl ring-1 ring-outline/20">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-primary">Portal Laravel</p>
                <h1 class="mt-2 font-headline text-3xl font-extrabold tracking-tight text-on-background">
                    Dashboard Awal
                </h1>
                <p class="mt-3 text-sm text-on-surface-variant">
                    Autentikasi mahasiswa dan admin sudah ditangani penuh oleh Laravel.
                    Flask diposisikan hanya sebagai service internal machine learning.
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition hover:brightness-110"
                >
                    Logout
                </button>
            </form>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <section class="rounded-2xl bg-surface-container p-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant">Nama</p>
                <p class="mt-2 text-lg font-semibold text-on-background">{{ $user->name }}</p>
            </section>

            <section class="rounded-2xl bg-surface-container p-5">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant">Role</p>
                <p class="mt-2 text-lg font-semibold text-on-background">{{ $user->role }}</p>
            </section>

            <section class="rounded-2xl bg-surface-container p-5 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant">Email</p>
                <p class="mt-2 text-lg font-semibold text-on-background">{{ $user->email }}</p>
            </section>
        </div>
    </div>
</main>
@endsection
