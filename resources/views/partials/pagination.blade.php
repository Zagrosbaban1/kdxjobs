@if ($paginator->hasPages())
    <nav class="actions" style="margin-top: 18px;" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn muted" aria-disabled="true">Previous</span>
        @else
            <a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        <span class="muted tiny" style="align-self:center;">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="btn muted" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
