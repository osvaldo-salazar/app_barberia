<?php

namespace App\Http\Controllers;

use App\Models\{CashClosing, CashEntry};
use App\Services\CashBook;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function __construct(private CashBook $book) {}

    public function show(Request $request)
    {
        $date = $this->date($request);
        $f    = $this->figures($date);

        $f['history']         = CashClosing::orderByDesc('date')->limit(14)->get();
        $f['computedOpening'] = $this->book->opening($date);
        $f['date']            = $date;

        return view('caja.show', $f);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'         => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'opening_cash' => ['required', 'integer', 'min:0', 'max:100000000'],
            'counted_cash' => ['required', 'integer', 'min:0', 'max:100000000'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ], [
            'date.before_or_equal'  => 'No se puede cerrar la caja de un día futuro.',
            'opening_cash.required' => 'Escribe con cuánto empezó la caja.',
            'opening_cash.integer'  => 'La caja inicial debe ser un número entero, sin decimales.',
            'counted_cash.required' => 'Escribe cuánto efectivo contaste.',
            'counted_cash.integer'  => 'El efectivo contado debe ser un número entero, sin decimales.',
        ]);

        $date      = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $s         = $this->book->day($date);
        $m         = $this->book->moves($date);
        $cashSales = $s['byMethod']['efectivo']['amount'];
        $expected  = $data['opening_cash'] + $cashSales + $m['net'];

        CashClosing::updateOrCreate(['date' => $data['date']], [
            'opening_cash'   => $data['opening_cash'],
            'cash_moves'     => $m['net'],
            'expected_cash'  => $cashSales,
            'expected_sinpe' => $s['byMethod']['sinpe']['amount'],
            'expected_card'  => $s['byMethod']['tarjeta']['amount'],
            'tips'           => $s['tips'],
            'counted_cash'   => $data['counted_cash'],
            'difference'     => $data['counted_cash'] - $expected,
            'notes'          => $data['notes'] ?? null,
            'closed_by'      => auth()->id(),
        ]);

        return redirect()->route('caja', ['fecha' => $data['date']])->with('ok', 'Cierre de caja guardado.');
    }

    public function pdf(Request $request)
    {
        $date = $this->date($request);
        $f    = $this->figures($date);
        $f['date'] = $date;

        return Pdf::loadView('pdf.day', $f)->setPaper('a4')
            ->download('caja-' . $date->toDateString() . '.pdf');
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'date'    => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'type'    => ['required', 'in:entrada,salida'],
            'amount'  => ['required', 'integer', 'min:1', 'max:100000000'],
            'concept' => ['required', 'string', 'max:80'],
        ], [
            'type.required'    => 'Elige si es una entrada o una salida.',
            'amount.required'  => 'Escribe el monto.',
            'amount.integer'   => 'El monto debe ser un número entero, sin decimales.',
            'amount.min'       => 'El monto debe ser mayor a cero.',
            'concept.required' => 'Escribe el concepto del movimiento.',
        ]);

        CashEntry::create($data + ['user_id' => auth()->id()]);

        return redirect()->route('caja', ['fecha' => $data['date']])->with('ok', 'Movimiento registrado.');
    }

    public function destroyEntry(CashEntry $entry)
    {
        $date = $entry->date->toDateString();
        $entry->delete();

        return redirect()->route('caja', ['fecha' => $date])->with('ok', 'Movimiento eliminado.');
    }

    /* ---------- Helpers ---------- */

    private function date(Request $request): Carbon
    {
        $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);

        return $request->filled('fecha')
            ? Carbon::createFromFormat('Y-m-d', $request->fecha)->startOfDay()
            : today();
    }

    /** Todo lo que necesitan la pantalla y el PDF del día */
    private function figures(Carbon $date): array
    {
        $s       = $this->book->day($date);
        $m       = $this->book->moves($date);
        $closing = CashClosing::with('closer')->where('date', $date->toDateString())->first();

        $opening   = $closing ? (int) $closing->opening_cash : $this->book->opening($date);
        $cashSales = $s['byMethod']['efectivo']['amount'];
        $expected  = $opening + $cashSales + $m['net'];

        // ¿Cambió algo en las citas o movimientos después de guardar el cierre?
        $stale = $closing && (
            (int) $closing->expected_cash  !== $cashSales ||
            (int) $closing->expected_sinpe !== $s['byMethod']['sinpe']['amount'] ||
            (int) $closing->expected_card  !== $s['byMethod']['tarjeta']['amount'] ||
            (int) $closing->tips           !== $s['tips'] ||
            (int) $closing->cash_moves     !== $m['net']
        );

        return compact('s', 'm', 'closing', 'opening', 'cashSales', 'expected', 'stale');
    }
}