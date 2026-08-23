@extends('layouts.app')

@section('title', '@' . $profile->username . ' — ' . config('app.name'))
@section('subtitle', 'Último sync: ' . ($profile->last_synced_at?->diffForHumans() ?? 'nunca'))

@section('content')
    <a href="{{ route('profiles.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; voltar</a>

    <div class="mt-4 bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center gap-5">
            @if ($profile->profile_pic_url)
                <img src="{{ $profile->profile_pic_url }}" alt="" referrerpolicy="no-referrer"
                     class="w-20 h-20 rounded-full object-cover bg-slate-100">
            @endif
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold">@{{ $profile->username }}</h1>
                    @if ($profile->is_verified)
                        <span class="text-blue-500" title="Verified">✓</span>
                    @endif
                </div>
                @if ($profile->full_name)
                    <div class="text-slate-600">{{ $profile->full_name }}</div>
                @endif
                @if ($profile->biography)
                    <p class="text-sm text-slate-500 mt-1 whitespace-pre-line">{{ $profile->biography }}</p>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold">{{ number_format($profile->followers_count ?? 0, 0, ',', '.') }}</div>
                <div class="text-xs uppercase tracking-wide text-slate-500">followers</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold">{{ number_format($profile->following_count ?? 0, 0, ',', '.') }}</div>
                <div class="text-xs uppercase tracking-wide text-slate-500">following</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold">{{ number_format($profile->media_count ?? 0, 0, ',', '.') }}</div>
                <div class="text-xs uppercase tracking-wide text-slate-500">posts</div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs uppercase text-slate-500">Novos (24h)</div>
            <div class="text-xl font-bold text-emerald-600">+{{ $totals['follows_24h'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs uppercase text-slate-500">Unfollows (24h)</div>
            <div class="text-xl font-bold text-rose-600">-{{ $totals['unfollows_24h'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs uppercase text-slate-500">Novos (7d)</div>
            <div class="text-xl font-bold text-emerald-600">+{{ $totals['follows_7d'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs uppercase text-slate-500">Unfollows (7d)</div>
            <div class="text-xl font-bold text-rose-600">-{{ $totals['unfollows_7d'] }}</div>
        </div>
    </div>

    @if ($countHistory->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="font-semibold mb-4">Evolução</h2>
            <canvas id="chart" height="90"></canvas>
        </div>
    @endif

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Actividade recente</h2>
            @if ($recentEvents->isEmpty())
                <p class="text-sm text-slate-500">Ainda sem eventos registados. O primeiro sync só grava eventos após o baseline.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentEvents as $ev)
                        <li class="py-2 flex items-center gap-3">
                            @if ($ev->profile_pic_url)
                                <img src="{{ $ev->profile_pic_url }}" alt="" referrerpolicy="no-referrer"
                                     class="w-8 h-8 rounded-full object-cover bg-slate-100">
                            @else
                                <div class="w-8 h-8 rounded-full bg-slate-200"></div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <a href="https://www.instagram.com/{{ $ev->username }}/" target="_blank"
                                   class="text-sm font-medium hover:underline truncate block">@{{ $ev->username }}</a>
                                @if ($ev->full_name)
                                    <div class="text-xs text-slate-500 truncate">{{ $ev->full_name }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                @if ($ev->event_type === 'follow')
                                    <span class="text-xs font-semibold text-emerald-600">+follow</span>
                                @else
                                    <span class="text-xs font-semibold text-rose-600">-unfollow</span>
                                @endif
                                <div class="text-[10px] text-slate-400">{{ $ev->occurred_at->diffForHumans() }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Últimos unfollows</h2>
            @if ($recentlyLost->isEmpty())
                <p class="text-sm text-slate-500">Nenhum unfollow registado ainda.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentlyLost as $f)
                        <li class="py-2 flex items-center gap-3">
                            @if ($f->profile_pic_url)
                                <img src="{{ $f->profile_pic_url }}" alt="" referrerpolicy="no-referrer"
                                     class="w-8 h-8 rounded-full object-cover bg-slate-100">
                            @else
                                <div class="w-8 h-8 rounded-full bg-slate-200"></div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <a href="https://www.instagram.com/{{ $f->username }}/" target="_blank"
                                   class="text-sm font-medium hover:underline truncate block">@{{ $f->username }}</a>
                                @if ($f->full_name)
                                    <div class="text-xs text-slate-500 truncate">{{ $f->full_name }}</div>
                                @endif
                            </div>
                            <div class="text-[10px] text-slate-400 text-right">
                                {{ $f->unfollowed_at?->diffForHumans() }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="font-semibold mb-3">Followers actuais mais recentes</h2>
        @if ($activeFollowers->isEmpty())
            <p class="text-sm text-slate-500">Sem dados.</p>
        @else
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                @foreach ($activeFollowers as $f)
                    <a href="https://www.instagram.com/{{ $f->username }}/" target="_blank"
                       class="flex flex-col items-center text-center hover:opacity-80">
                        @if ($f->profile_pic_url)
                            <img src="{{ $f->profile_pic_url }}" alt="" referrerpolicy="no-referrer"
                                 class="w-14 h-14 rounded-full object-cover bg-slate-100">
                        @else
                            <div class="w-14 h-14 rounded-full bg-slate-200"></div>
                        @endif
                        <span class="text-xs mt-1 truncate w-full">@{{ $f->username }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($countHistory->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            const data = @json($countHistory);
            const labels = data.map(d => new Date(d.at).toLocaleString('pt-PT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }));
            new Chart(document.getElementById('chart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Followers',
                        data: data.map(d => d.count),
                        borderColor: 'rgb(59,130,246)',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        tension: 0.3,
                        pointRadius: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: false } }
                }
            });
        </script>
    @endif
@endsection
