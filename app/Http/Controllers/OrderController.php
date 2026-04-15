<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\CancelOrderRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * Daftar semua order (Admin: semua, Kasir: milik sendiri)
     */
    public function index()
    {
        $orders = Auth::user()->isAdmin
            ? Order::with(['cashier', 'orderItems'])
                ->latest()->paginate(15)
            : Order::where('cashier_id', Auth::id())
                ->with('orderItems')
                ->latest()->paginate(15);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Detail satu order
     */
    public function show(Order $order)
    {
        $order->load(['cashier', 'orderItems.batch.product', 'productReturn']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    /**
     * Kasir buat transaksi baru
     */
    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->createOrder(
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('orders.show', $order)
            ->with('success', "Transaksi {$order->invoice_number} berhasil disimpan.");
    }

    /**
     * Admin cancel order
     */
    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $this->orderService->cancelOrder($order, Auth::user());

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order berhasil dibatalkan. Stok sudah dikembalikan.');
    }
}