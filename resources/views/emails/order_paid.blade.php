@if ($forOffice)
<p>Pedido pagado: <strong>{{ $order->id }}</strong> ({{ $order->email }}).</p>
@else
<p>Gracias por tu compra. Hemos recibido el pago de tu pedido <strong>{{ $order->id }}</strong>.</p>
@endif
<ul>
    @foreach ($order->items as $item)
        <li>{{ $item->quantity }} × {{ $item->name }} — {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }} € (IVA incluido)</li>
    @endforeach
</ul>
<p><strong>Total: {{ number_format($order->amount_cents / 100, 2, ',', '.') }} €</strong></p>
@if (! $forOffice && count($links))
    <p>Elige día y hora para cada consulta con estos enlaces (son personales y de un solo uso):</p>
    <ul>
        @foreach ($links as $link)
            <li><a href="{{ $link['url'] }}">{{ $link['name'] }}</a></li>
        @endforeach
    </ul>
@endif
@if ($forOffice && $order->note)
    <p>Nota del cliente: {{ $order->note }}</p>
@endif
