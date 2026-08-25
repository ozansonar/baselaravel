@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_description', 'Genel bakış ve istatistikler')

@section('content')

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between" data-aos="fade-down" data-aos-duration="400">
        <div>
            <h2>Dashboard</h2>
            <p>Hoş geldiniz, {{ auth()->user()->name }}. Sitenizin genel durumunu buradan takip edin.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.blog-posts.create') }}" class="btn-teal">
                <i class="bi bi-plus-lg"></i> Yeni İçerik
            </a>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
            <div class="stat-card teal animate-in">
                <div class="stat-card-header">
                    <div class="stat-icon teal"><i class="bi bi-people-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['total_users'] }}">0</div>
                <div class="stat-label">Kullanıcılar</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card purple animate-in anim-delay-1">
                <div class="stat-card-header">
                    <div class="stat-icon purple"><i class="bi bi-journal-richtext"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['total_posts'] }}">0</div>
                <div class="stat-label">Blog Yazıları</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card blue animate-in anim-delay-2">
                <div class="stat-card-header">
                    <div class="stat-icon blue"><i class="bi bi-file-earmark-text-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['total_pages'] }}">0</div>
                <div class="stat-label">Sayfalar</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card orange animate-in anim-delay-3">
                <div class="stat-card-header">
                    <div class="stat-icon orange"><i class="bi bi-envelope-fill"></i></div>
                </div>
                <div class="stat-value" data-count="{{ $stats['unread_messages'] }}">0</div>
                <div class="stat-label">Okunmamış Mesaj</div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: RECENT MESSAGES + RECENT POSTS ==================== -->
    <div class="row g-3 mb-4">
        <!-- Recent Messages -->
        <div class="col-xl-6" data-aos="fade-up" data-aos-delay="50">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-envelope me-2 text-teal"></i>Son Mesajlar</h6>
                    <a href="{{ route('admin.contact-messages.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom pt-2">
                    @forelse($recentMessages as $msg)
                        <div class="activity-item">
                            <div class="activity-icon bg-icon-{{ $msg->is_read ? 'green' : 'orange' }}-strong">
                                <i class="bi bi-{{ $msg->is_read ? 'envelope-open' : 'envelope-fill' }}"></i>
                            </div>
                            <div class="activity-content">
                                <h6>{{ $msg->name }}</h6>
                                <p>{{ $msg->created_at->diffForHumans() }} — {{ Str::limit($msg->subject, 40) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-clr-secondary mb-0 text-center py-3">Henüz mesaj yok.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="col-xl-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card-dark">
                <div class="card-header-custom">
                    <h6><i class="bi bi-journal-richtext me-2 text-neon-purple"></i>Son Blog Yazıları</h6>
                    <a href="{{ route('admin.blog-posts.index') }}" class="btn-glass btn-sm">Tümünü Gör</a>
                </div>
                <div class="card-body-custom pt-2">
                    @forelse($recentPosts as $post)
                        <div class="activity-item">
                            <div class="activity-icon bg-icon-teal-strong">
                                <i class="bi bi-file-earmark-post"></i>
                            </div>
                            <div class="activity-content">
                                <h6>{{ Str::limit($post->title, 40) }}</h6>
                                <p>{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-clr-secondary mb-0 text-center py-3">Henüz blog yazısı yok.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/dashboard.js') }}"></script>
@endpush
