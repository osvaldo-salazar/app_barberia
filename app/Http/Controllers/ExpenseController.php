<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, Expense};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['mes' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $month = $request->filled('mes')
            ? Carbon::createFromFormat('Y-m-d', $request->mes . '-01')->startOfDay()
            : now()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $expenses = Expense::whereBetween('date', [$month->toDateString(), $end->toDateString()])
            ->orderByDesc('date')->orderByDesc('id')->get();

        $byCategory = $expenses->groupBy('category')->map->sum('amount')->sortDesc();
        $total      = (int) $expenses->sum('amount');
        $owner      = (int) Appointment::where('status', 'completada')
            ->whereBetween('starts_at', [$month, $end])->sum('owner_amount');

        return view('gastos.index', [
            'month' => $month, 'expenses' => $expenses, 'byCategory' => $byCategory,
            'total' => $total, 'owner' => $owner, 'profit' => $owner - $total,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'     => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'concept'  => ['required', 'string', 'max:80'],
            'amount'   => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'date.before_or_equal' => 'La fecha del gasto no puede ser futura.',
            'category.required'    => 'Elige una categoría.',
            'concept.required'     => 'Escribe el concepto del gasto.',
            'amount.required'      => 'Escribe el monto.',
            'amount.integer'       => 'El monto debe ser un número entero, sin decimales.',
            'amount.min'           => 'El monto debe ser mayor a cero.',
        ]);

        $data['from_cash'] = $request->boolean('from_cash');
        $data['user_id']   = auth()->id();

        Expense::create($data);

        return redirect()->route('gastos.index', ['mes' => substr($data['date'], 0, 7)])->with('ok', 'Gasto registrado.');
    }

    public function destroy(Expense $expense)
    {
        $month = $expense->date->format('Y-m');
        $expense->delete();

        return redirect()->route('gastos.index', ['mes' => $month])->with('ok', 'Gasto eliminado.');
    }
}