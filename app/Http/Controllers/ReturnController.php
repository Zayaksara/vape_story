<?php

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use App\Models\Order;
use App\Services\ReturnService;
use App\Http\Requests\CreateReturnRequest;
use App\Http\Requests\ApproveReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    /**
     * List semua return requests (Admin: semua, Kasir: milik sendiri)
     */
    public function index()
    {
        $returns = Auth::user()->isAdmin()
            ? ProductReturn::with(['order', 'cashier', 'approvedBy', 'returnItems'])
                ->latest()->paginate(15)
            : ProductReturn::where('cashier_id', Auth::id())
                ->with(['order', 'returnItems'])
                ->latest()->paginate(15);

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
        ]);
    }

    /**
     * Detail satu return
     */
    public function show(ProductReturn $return)
    {
        $return->load(['order.orderItems', 'cashier', 'approvedBy', 'returnItems.batch']);

        return Inertia::render('Returns/Show', [
            'return' => $return,
        ]);
    }

    /**
     * Kasir membuat return request
     */
    public function store(CreateReturnRequest $request)
    {
        $order = Order::findOrFail($request->order_id);

        $productReturn = $this->returnService->createReturn(
            $order,
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('returns.show', $productReturn)
            ->with('success', 'Return request berhasil dibuat. Menunggu approval admin.');
    }

    /**
     * Admin approve return
     */
    public function approve(ApproveReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->approveReturn($return, Auth::user());

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-approve. Stok sudah dikembalikan.');
    }

    /**
     * Admin reject return
     */
    public function reject(RejectReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->rejectReturn($return, Auth::user(), $request->reason);

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-reject.');
    }
}