@extends('layouts.app')

@section('title', 'Perfis — ' . config('app.name'))

@section('content')
    <h1 class="text-2xl font-bold mb-6">Perfis monitorizados</h1>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($rows as $row)
            @php
                $p = $row['profile'];
                $run = $row['last_run'];
            @endphp
            <a href="{{ route('profiles.show', $row['username']) }}"
               class="block bg-white rounded-xl border border-slate-200 hover:border-slate-400 transition p-5">
                <div class="flex items-center gap-4">
                    @if ($p?->profile_pic_url)
                        <img src="@igimg($p->profile_pic_url)" alt="" loading="lazy"
                             class="w-14 h-14 rounded-full object-cover bg-slate-100">
                    @else
                        <div class="w-14 h-14 rounded-full bg-slate-200"></div>
                    @endif
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <span class="font-semibold truncate">{{ '@'.$row['username'] }}</span>
                            @if ($p?->is_verified)
                                <span class="text-blue-500 text-xs" title="Verified">✓</span>
                            @endif
                        </div>
                        @if ($p?->full_name)
                            <div class="text-sm text-slate-500 truncate">{{ $p->full_name }}</div>
                        @endif
                    </div>
                </div>

                @if ($p)
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div>
                            <div class="text-lg font-semibold">{{ number_format($p->followers_count ?? 0, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-500">followers</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold">{{ number_format($p->following_count ?? 0, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-500">following</div>
                        </div>
                        <div>
                            <div class="text-lg font-semibold">{{ number_format($p->media_count ?? 0, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-500">posts</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-slate-400">
                        Último sync: {{ $p->last_synced_at?->diffForHumans() ?? 'nunca' }}
                    </div>
                @else
                    <div class="mt-4 text-sm text-amber-600">Aguarda primeiro sync…</div>
                @endif
            </a>
        @endforeach
    </div>
@endsection
