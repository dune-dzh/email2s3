@php
    $sort = $sort ?? 'id';
    $sortDir = $sortDir ?? 'asc';
    // Base query for links: exclude 'partial' so sort/pagination trigger full page load, not partial-only response.
    $baseQuery = request()->except('partial');
@endphp
<div class="emails-list-partial" data-total="{{ $emails->total() }}">
<div class="table-wrap">
<table>
    <colgroup>
        <col class="col-id">
        <col class="col-sender">
        <col class="col-receiver">
        <col class="col-subject">
        <col class="col-created">
        <col class="col-status">
        <col class="col-attachments">
        <col class="col-body">
    </colgroup>
    <thead>
    <tr>
        <th class="th-sortable">
            @php $q = array_merge($baseQuery, ['sort' => 'id', 'dir' => ($sort === 'id' && $sortDir === 'asc') ? 'desc' : 'asc']); @endphp
            <a href="{{ route('emails.index', $q) }}" class="sort-link {{ $sort === 'id' ? 'active' : '' }}">ID<span class="sort-arrow">{{ $sort === 'id' ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '' }}</span></a>
        </th>
        <th class="th-sortable">
            @php $q = array_merge($baseQuery, ['sort' => 'sender_email', 'dir' => ($sort === 'sender_email' && $sortDir === 'asc') ? 'desc' : 'asc']); @endphp
            <a href="{{ route('emails.index', $q) }}" class="sort-link {{ $sort === 'sender_email' ? 'active' : '' }}">Sender<span class="sort-arrow">{{ $sort === 'sender_email' ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '' }}</span></a>
        </th>
        <th class="th-sortable">
            @php $q = array_merge($baseQuery, ['sort' => 'receiver_email', 'dir' => ($sort === 'receiver_email' && $sortDir === 'asc') ? 'desc' : 'asc']); @endphp
            <a href="{{ route('emails.index', $q) }}" class="sort-link {{ $sort === 'receiver_email' ? 'active' : '' }}">Receiver<span class="sort-arrow">{{ $sort === 'receiver_email' ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '' }}</span></a>
        </th>
        <th class="th-sortable">
            @php $q = array_merge($baseQuery, ['sort' => 'subject', 'dir' => ($sort === 'subject' && $sortDir === 'asc') ? 'desc' : 'asc']); @endphp
            <a href="{{ route('emails.index', $q) }}" class="sort-link {{ $sort === 'subject' ? 'active' : '' }}">Subject<span class="sort-arrow">{{ $sort === 'subject' ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '' }}</span></a>
        </th>
        <th class="th-sortable">
            @php $q = array_merge($baseQuery, ['sort' => 'created_at', 'dir' => ($sort === 'created_at' && $sortDir === 'asc') ? 'desc' : 'asc']); @endphp
            <a href="{{ route('emails.index', $q) }}" class="sort-link {{ $sort === 'created_at' ? 'active' : '' }}">Created at<span class="sort-arrow">{{ $sort === 'created_at' ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '' }}</span></a>
        </th>
        <th>Status</th>
        <th>Attachments</th>
        <th>Body</th>
    </tr>
    </thead>
    <tbody>
    @forelse($emails as $email)
        <tr>
            <td class="td-id">#{{ $email->id }}</td>
            <td>{{ $email->sender_email }}</td>
            <td>{{ $email->receiver_email }}</td>
            <td>{{ \Illuminate\Support\Str::limit($email->subject, 40) }}</td>
            <td>{{ $email->created_at }}</td>
            <td>
                @php $state = (int) $email->is_migrated_s3; @endphp
                @if($state === 2)
                    <span class="status-pill status-migrated">Migrated</span>
                @elseif($state === 1)
                    <span class="status-pill status-migrating">Migrating</span>
                @else
                    <span class="status-pill status-pending">Pending</span>
                @endif
            </td>
            <td class="attachments-cell">
                @php
                    $ids = $email->file_ids ? (array) json_decode($email->file_ids, true) : [];
                    $isMigrated = (int) $email->is_migrated_s3 === 2;
                @endphp
                @if(count($ids) === 0)
                    <span class="muted">—</span>
                @else
                    <ul class="attachment-list">
                        @foreach($ids as $fileId)
                            @php
                                $fileId = (int) $fileId;
                                $info = $fileMap[$fileId] ?? null;
                            @endphp
                            @if($info)
                                <li>
                                    <a href="{{ route('emails.attachments.download', ['email' => $email->id, 'file' => $fileId]) }}"
                                       class="attachment-link"
                                       title="{{ $info['name'] }} ({{ number_format($info['size'] / 1024, 1) }} KB)">
                                        {{ \Illuminate\Support\Str::limit($info['name'], 20) }}
                                    </a>
                                    <span class="attachment-source">{{ $isMigrated ? 'S3' : 'local' }}</span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </td>
            <td>
                <button type="button" class="body-view-btn" data-email-id="{{ $email->id }}" data-subject="{{ e($email->subject) }}" title="View email body">View</button>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="8">No emails found.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</div>

@if ($emails->hasPages())
    <div class="pagination">
        @if ($emails->onFirstPage())
            <span>&laquo; Prev</span>
        @else
            <a href="{{ route('emails.index', array_merge($baseQuery, ['page' => $emails->currentPage() - 1])) }}">&laquo; Prev</a>
        @endif

        <span class="active">Page {{ $emails->currentPage() }} of {{ $emails->lastPage() }}</span>

        @if ($emails->hasMorePages())
            <a href="{{ route('emails.index', array_merge($baseQuery, ['page' => $emails->currentPage() + 1])) }}">Next &raquo;</a>
        @else
            <span>Next &raquo;</span>
        @endif
    </div>
@endif
</div>
