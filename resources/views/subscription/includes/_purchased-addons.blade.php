@if(isset($purchasedAddons) && $purchasedAddons->count())
    <div class="row">
        <div class="col-12">
            <div class="info-card">
                <div class="row">
                    <div class="col-12">
                        <ul class="list-group">
                            @foreach($purchasedAddons as $addonOrder)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $addonOrder->package->name ?? (is_array($addonOrder->metadata) && isset($addonOrder->metadata['addon']) ? ucwords(str_replace(['_', '-'], ' ', $addonOrder->metadata['addon'])) : 'Add-on') }}</strong>
                                        <br>
                                        <small class="text-muted">Purchased on {{ $addonOrder->created_at->format('F j, Y') }}</small>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge badge-success px-3 py-2">Active</span>
                                        <span class="badge badge-info px-3 py-2 ml-1">No expiry</span>
                                        @if(!empty($addonOrder->amount))
                                            <br>
                                            <small class="text-muted">Paid ${{ number_format($addonOrder->amount, 2) }} {{ $addonOrder->currency }}</small>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

