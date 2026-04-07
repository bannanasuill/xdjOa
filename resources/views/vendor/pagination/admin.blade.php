@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="分页">
        <ul class="admin-pagination__list">
            @if ($paginator->onFirstPage())
                <li class="admin-pagination__item admin-pagination__item--disabled"><span class="admin-pagination__btn">上一页</span></li>
            @else
                <li class="admin-pagination__item"><a class="admin-pagination__btn admin-pagination__btn--link" href="{{ $paginator->previousPageUrl() }}" rel="prev">上一页</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="admin-pagination__item admin-pagination__item--ellipsis"><span class="admin-pagination__btn">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="admin-pagination__item admin-pagination__item--active"><span class="admin-pagination__btn" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li class="admin-pagination__item"><a class="admin-pagination__btn admin-pagination__btn--link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="admin-pagination__item"><a class="admin-pagination__btn admin-pagination__btn--link" href="{{ $paginator->nextPageUrl() }}" rel="next">下一页</a></li>
            @else
                <li class="admin-pagination__item admin-pagination__item--disabled"><span class="admin-pagination__btn">下一页</span></li>
            @endif
        </ul>
    </nav>
@endif
