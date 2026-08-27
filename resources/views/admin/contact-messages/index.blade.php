@extends('layouts.admin')

@section('title', 'İletişim Mesajları')
@section('page_title', 'İletişim Mesajları')
@section('page_description', $unreadCount > 0 ? $unreadCount . ' okunmamış mesaj' : 'Tüm mesajlar')

@section('content')

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
            </li>
            <li class="breadcrumb-item active text-teal">İletişim Mesajları</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
            <h1 class="page-title">İletişim Mesajları</h1>
            <p class="page-subtitle">
                @if($unreadCount > 0)
                    {{ $unreadCount }} okunmamış mesaj var
                @else
                    Gelen mesajlarınızı yönetin
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="{{ route('admin.contact-messages.mark-all-read') }}" id="markAllReadForm">
                @csrf
                <button type="submit" class="btn-glass" {{ $unreadCount === 0 ? 'disabled' : '' }}>
                    <i class="bi bi-envelope-open me-1"></i> Tümünü Okundu Yap
                </button>
            </form>
        </div>
    </div>


    <!-- ==================== SECTION 1: STAT CARDS ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-blue">
                    <i class="bi bi-chat-left-text-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Toplam Mesaj</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['total'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="100">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-orange">
                    <i class="bi bi-envelope-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Okunmamış</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['unread'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="200">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-green">
                    <i class="bi bi-envelope-open-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Okunmuş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['read'] }}">0</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="300">
            <div class="usr-stat-card">
                <div class="usr-stat-icon usr-stat-icon-red">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <div class="usr-stat-info">
                    <span class="usr-stat-label">Silinmiş</span>
                    <h3 class="usr-stat-value" data-count="{{ $stats['trashed'] }}">0</h3>
                </div>
            </div>
        </div>
    </div>


    <!-- ==================== SECTION 2: MAIL LAYOUT ==================== -->
    @php
        $currentStatus = request('status', '');
        $totalCount = $stats['total'];
    @endphp

    <div class="msg-layout" data-aos="fade-up" data-aos-delay="100">

        <!-- LEFT: Folder Sidebar -->
        <aside class="msg-folders" id="msgFolders">
            <div class="msg-folders-inner">
                <a href="{{ route('admin.contact-messages.index', request()->except(['status', 'page'])) }}"
                   class="msg-folder-btn {{ $currentStatus === '' ? 'active' : '' }}">
                    <i class="bi bi-inbox"></i><span>Gelen Kutusu</span>
                    @if($totalCount > 0)
                        <span class="msg-folder-count">{{ $totalCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.contact-messages.index', array_merge(request()->except(['status', 'page']), ['status' => 'unread'])) }}"
                   class="msg-folder-btn {{ $currentStatus === 'unread' ? 'active' : '' }}">
                    <i class="bi bi-envelope"></i><span>Okunmamış</span>
                    @if(($statusCounts['unread'] ?? 0) > 0)
                        <span class="msg-folder-count">{{ $statusCounts['unread'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.contact-messages.index', array_merge(request()->except(['status', 'page']), ['status' => 'read'])) }}"
                   class="msg-folder-btn {{ $currentStatus === 'read' ? 'active' : '' }}">
                    <i class="bi bi-envelope-open"></i><span>Okunmuş</span>
                </a>
                <a href="{{ route('admin.contact-messages.index', array_merge(request()->except(['status', 'page']), ['status' => 'trashed'])) }}"
                   class="msg-folder-btn {{ $currentStatus === 'trashed' ? 'active' : '' }}">
                    <i class="bi bi-trash3"></i><span>Silinmiş</span>
                    @if(($statusCounts['trashed'] ?? 0) > 0)
                        <span class="msg-folder-count">{{ $statusCounts['trashed'] }}</span>
                    @endif
                </a>
            </div>
        </aside>

        <!-- CENTER: Message List -->
        <div class="msg-list-panel" id="msgListPanel">

            <div class="msg-list-header">
                <div class="d-flex align-items-center gap-2">
                    <button class="msg-mobile-back d-lg-none" onclick="toggleFolders()"><i class="bi bi-list"></i></button>
                    <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="msg-search" id="searchForm">
                        @if(request('status'))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        @if(request('per_page'))
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                        @endif
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" placeholder="Mesajlarda ara..." value="{{ request('search') }}" id="msgSearchInput" data-fv-ignore>
                    </form>
                </div>
                <div class="msg-list-actions">
                    @if(request('search'))
                        <a href="{{ route('admin.contact-messages.index', request()->except(['search', 'page'])) }}" class="msg-action-sm" title="Aramayı Temizle"><i class="bi bi-x-circle"></i></a>
                    @endif
                    <select class="msg-sort-select" id="perPageSelect" onchange="changePerPage(this.value)" data-fv-ignore>
                        @foreach([10, 25, 50, 100] as $pp)
                            <option value="{{ $pp }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }} mesaj</option>
                        @endforeach
                    </select>

                    <x-export-menu export="contact-messages" :total="$messages->total()" />
                </div>
            </div>

            <!-- Message Items -->
            <div class="msg-list" id="msgList">
                @forelse($messages as $message)
                    <div class="msg-item {{ !$message->is_read ? 'unread' : '' }} {{ $message->trashed() ? 'draft' : '' }}"
                         data-id="{{ $message->id }}"
                         onclick="openMessage({{ $message->id }})">
                        <div class="msg-item-check">
                            <input type="checkbox" class="usr-checkbox msg-checkbox" value="{{ $message->id }}" onclick="event.stopPropagation(); toggleBulk()" data-fv-ignore>
                        </div>
                        <div class="msg-avatar msg-avatar--{{ $message->trashed() ? 'gray' : ($message->is_read ? 'teal' : 'orange') }}">
                            {{ strtoupper(mb_substr($message->name, 0, 1)) }}{{ strtoupper(mb_substr(explode(' ', $message->name)[1] ?? '', 0, 1)) }}
                        </div>
                        <div class="msg-item-content">
                            <div class="msg-item-top">
                                <span class="msg-sender">{{ $message->name }}</span>
                                @if($message->trashed())
                                    <span class="msg-label-tag msg-label-tag--important">Silinmiş</span>
                                @endif
                                <span class="msg-time">{{ $message->created_at->diffForHumans(short: true) }}</span>
                            </div>
                            <div class="msg-subject">{{ $message->subject }}</div>
                            <div class="msg-preview">{{ Str::limit($message->message, 80) }}</div>
                        </div>
                        <div class="msg-item-actions">
                            @if($message->trashed())
                                <form method="POST" action="{{ route('admin.contact-messages.restore', $message->id) }}" onclick="event.stopPropagation()">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" title="Geri Yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            @else
                                <button onclick="event.stopPropagation(); openDeleteModal({{ $message->id }}, '{{ addslashes($message->name) }}')" title="Sil"><i class="bi bi-trash3"></i></button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="msg-empty">
                        <i class="bi bi-inbox"></i>
                        <h5>Mesaj bulunamadı</h5>
                        <p>Bu klasörde henüz mesaj yok</p>
                    </div>
                @endforelse
            </div>

            <!-- Bulk Actions -->
            <div class="msg-bulk-bar" id="msgBulkBar">
                <span class="msg-bulk-count"><span id="bulkCount">0</span> mesaj seçildi</span>
                <div class="d-flex gap-2">
                    <button class="btn-glass btn-sm" onclick="bulkDelete()"><i class="bi bi-trash3 me-1"></i>Sil</button>
                </div>
            </div>

            @include('partials.admin.pagination', ['paginator' => $messages, 'itemLabel' => 'mesaj'])

        </div>

        <!-- RIGHT: Detail Panel -->
        <div class="msg-detail-panel" id="msgDetailPanel">
            <div class="msg-detail-empty" id="msgDetailEmpty">
                <i class="bi bi-envelope-open"></i>
                <h5>Mesaj Seçin</h5>
                <p>Okumak için listeden bir mesaj seçin</p>
            </div>

            <div class="msg-detail-content d-none" id="msgDetailContent">
                <div class="msg-detail-header">
                    <button class="msg-detail-back d-xl-none" onclick="closeDetail()"><i class="bi bi-arrow-left"></i></button>
                    <div class="msg-detail-actions">
                        <button onclick="toggleReplyForm()" id="detailReplyBtn" title="Yanıtla"><i class="bi bi-reply"></i></button>
                        <a href="" id="detailPhoneBtn" class="d-none" title="Telefon"><i class="bi bi-telephone"></i></a>
                        <button onclick="deleteDetail()" title="Sil"><i class="bi bi-trash3"></i></button>
                    </div>
                </div>

                <div class="msg-detail-body">
                    <h4 class="msg-detail-subject" id="detailSubject"></h4>
                    <div class="msg-detail-meta">
                        <div class="msg-detail-sender-avatar msg-avatar--teal" id="detailAvatar"></div>
                        <div class="msg-detail-sender-info">
                            <div>
                                <strong id="detailSender"></strong>
                                <span class="text-clr-secondary" id="detailEmail"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-clr-secondary" id="detailDate"></span>
                                <span class="cm-replied-badge d-none" id="detailRepliedBadge"><i class="bi bi-check2-all"></i> Yanıtlandı</span>
                            </div>
                        </div>
                    </div>

                    <div class="msg-detail-text" id="detailText"></div>

                    <!-- Sent Reply Display -->
                    <div class="cm-sent-reply d-none" id="sentReplySection">
                        <div class="cm-sent-reply-header">
                            <i class="bi bi-reply-fill"></i>
                            <span>Gönderilen Yanıt</span>
                            <span class="cm-sent-reply-date" id="sentReplyDate"></span>
                        </div>
                        <div class="cm-sent-reply-body" id="sentReplyText"></div>
                    </div>

                    <!-- Reply Form -->
                    <div class="cm-reply-section d-none" id="replySection">
                        <div class="cm-reply-header">
                            <i class="bi bi-reply-fill"></i>
                            <span>Yanıt Yaz</span>
                            <button class="cm-reply-close" onclick="toggleReplyForm()"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="cm-reply-to" id="replyToInfo"></div>
                        <textarea class="cm-reply-textarea" id="replyBody" rows="6" placeholder="Yanıtınızı buraya yazın..." data-fv-ignore></textarea>
                        <div class="cm-reply-footer">
                            <span class="cm-reply-hint"><i class="bi bi-info-circle"></i> Yanıt e-posta olarak gönderilecektir</span>
                            <div class="d-flex gap-2">
                                <button class="btn-glass btn-sm" onclick="toggleReplyForm()">İptal</button>
                                <button class="btn-teal btn-sm" id="sendReplyBtn" onclick="sendReply()">
                                    <i class="bi bi-send me-1"></i> Gönder
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-none" id="detailPhoneSection">
                        <div class="msg-attachments">
                            <div class="msg-attachments-title"><i class="bi bi-telephone"></i> <span>İletişim Bilgileri</span></div>
                            <div class="msg-attachment-list">
                                <div class="msg-attachment-item">
                                    <div class="msg-attachment-icon"><i class="bi bi-telephone-fill"></i></div>
                                    <div class="msg-attachment-info">
                                        <span class="msg-attachment-name" id="detailPhone"></span>
                                        <span class="msg-attachment-size">Telefon</span>
                                    </div>
                                    <a href="" class="msg-attachment-download" id="detailPhoneLink" title="Ara"><i class="bi bi-telephone-outbound"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-none" id="detailIpSection">
                        <div class="msg-attachments">
                            <div class="msg-attachments-title"><i class="bi bi-globe"></i> <span>Teknik Bilgi</span></div>
                            <div class="msg-attachment-list">
                                <div class="msg-attachment-item">
                                    <div class="msg-attachment-icon"><i class="bi bi-hdd-network-fill"></i></div>
                                    <div class="msg-attachment-info">
                                        <span class="msg-attachment-name" id="detailIp"></span>
                                        <span class="msg-attachment-size">IP Adresi</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/contact-messages.js') }}"></script>
@endpush
