
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary"
           href="{{ route('comisiones', ['fecha' => $prev]) }}">
            &laquo;
        </a>

        <div class="text-center">
            <h5 class="m-0">
                Semana del {{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }}
            </h5>
            <small class="text-muted">
                Pago: {{ $payDay->translatedFormat('l d/m') }} a las 7:00 PM
            </small>
        </div>

        <a class="btn btn-outline-secondary"
           href="{{ route('comisiones', ['fecha' => $next]) }}">
            &raquo;
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Barbero</th>
                    <th class="text-end">Servicios</th>
                    <th class="text-end">Producido</th>
                    <th class="text-end">Gana barbero</th>
                    <th class="text-end">Gana jefe</th>
                    <th class="text-end">Propinas</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->barber->name ?? 'Barbero eliminado' }}</td>
                        <td class="text-end">{{ $r->cuts }}</td>
                        <td class="text-end">
                            ₡{{ number_format($r->total, 0, ',', '.') }}
                        </td>
                        <td class="text-end fw-bold">
                            ₡{{ number_format($r->barber_total, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            ₡{{ number_format($r->owner_total, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            ₡{{ number_format($r->tips ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Sin servicios completados esta semana.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            <tfoot class="fw-bold">
                <tr>
                    <td colspan="2">TOTAL</td>
                    <td class="text-end">
                        ₡{{ number_format($totals['total'], 0, ',', '.') }}
                    </td>
                    <td class="text-end">
                        ₡{{ number_format($totals['barber'], 0, ',', '.') }}
                    </td>
                    <td class="text-end">
                        ₡{{ number_format($totals['owner'], 0, ',', '.') }}
                    </td>
                    <td class="text-end">
                        ₡{{ number_format($totals['tips'] ?? 0, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Resumen por método de pago --}}
    <h6 class="mt-4 mb-2">Ingresos por método de pago</h6>

    <div class="row g-2 mt-2">
        @foreach([
            'efectivo' => 'Efectivo',
            'sinpe' => 'SINPE',
            'tarjeta' => 'Tarjeta'
        ] as $k => $label)
            <div class="col-4">
                <div class="card text-center h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fw-bold">
                            ₡{{ number_format($byMethod[$k] ?? 0, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
