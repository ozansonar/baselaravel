@extends('layouts.app')

@section('title', __('site.comments.title') . ' | ' . \App\Models\Setting::getValue('site_name', config('app.name')))
@section('meta_description', __('site.comments.desc'))
@section('robots', 'noindex, nofollow')

@section('content')

    <section class="section">
        <div class="container">
            <div class="row g-4">

                {{-- Sidebar --}}
                <div class="col-lg-3">
                    @include('account.partials.sidebar', ['user' => $user])
                </div>

                {{-- Content --}}
                <div class="col-lg-9">
                    <span class="section__eyebrow"><i class="fa-solid fa-comments"></i> {{ __('site.auth.account') }}</span>
                    <h1 class="section__title">{{ __('site.comments.title') }}</h1>
                    <p class="section__lead mb-4">{{ __('site.comments.lead') }}</p>

                    <div class="field-card">
                        <ul class="device-list">
                            @forelse($comments as $comment)
                                <li class="device-list__item">
                                    <span class="device-list__icon"><i class="fa-solid fa-comment"></i></span>

                                    <div class="device-list__body">
                                        <div class="device-list__name">
                                            @if($comment->post)
                                                {{ $comment->post->title }}
                                            @else
                                                {{ __('site.comments.deleted_post') }}
                                            @endif

                                            @if($comment->status === \App\Enums\CommentStatus::Approved)
                                                <span class="badge text-bg-success ms-1">{{ __('site.comments.approved') }}</span>
                                            @elseif($comment->status === \App\Enums\CommentStatus::Pending)
                                                <span class="badge text-bg-warning ms-1">{{ __('site.comments.pending') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary ms-1">{{ __('site.comments.rejected') }}</span>
                                            @endif
                                        </div>

                                        <p class="mb-2">{{ $comment->body }}</p>

                                        <div class="device-list__meta">
                                            <span><i class="fa-regular fa-clock"></i> {{ $comment->created_at?->diffForHumans() }}</span>
                                            @if($comment->post && $comment->post->category && $comment->status === \App\Enums\CommentStatus::Approved)
                                                <a href="{{ localized_route('blog.show', ['categorySlug' => $comment->post->category->slug, 'slug' => $comment->post->slug]) }}#comments">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('site.comments.view') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <form action="{{ route('account.comments.destroy', $comment->id) }}" method="POST"
                                          class="device-list__action"
                                          data-confirm="{{ __('site.comments.confirm_delete') }}"
                                          data-confirm-title="{{ __('site.comments.delete') }}"
                                          data-confirm-btn="{{ __('site.comments.delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fa-solid fa-trash"></i> {{ __('site.comments.delete') }}
                                        </button>
                                    </form>
                                </li>
                            @empty
                                <li class="device-list__empty">{{ __('site.comments.empty') }}</li>
                            @endforelse
                        </ul>

                        @if($comments->hasPages())
                            <div class="mt-4">
                                {{ $comments->links() }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection
