<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Payments\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    use HandlesUploads;

    public function __construct(protected GatewayManager $gateways)
    {
    }

    public function index()
    {
        return view('admin.payment-methods.index', [
            'methods' => PaymentMethod::withCount('orders')->orderBy('sort_order')->orderBy('id')->get(),
            'drivers' => $this->gateways->options(),
        ]);
    }

    public function create(Request $request)
    {
        $type = in_array($request->query('type'), array_keys(PaymentMethod::types()), true) ? $request->query('type') : PaymentMethod::TYPE_MANUAL;

        return $this->form(new PaymentMethod([
            'type' => $type,
            'is_active' => true,
            'require_reference' => $type === PaymentMethod::TYPE_MANUAL,
            'require_proof' => $type === PaymentMethod::TYPE_MANUAL,
            'advance_type' => 'none',
            'details' => $type === PaymentMethod::TYPE_MANUAL ? [['label' => 'Account Title', 'value' => ''], ['label' => 'Account Number', 'value' => '']] : [],
        ]));
    }

    public function store(Request $request)
    {
        PaymentMethod::create($this->validated($request));

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method created.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return $this->form($paymentMethod);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($this->validated($request, $paymentMethod));

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method updated.');
    }

    public function toggle(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back()->with('success', $paymentMethod->name.' is now '.($paymentMethod->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->orders()->exists()) {
            $paymentMethod->update(['is_active' => false]);

            return back()->with('success', 'This method has orders, so it was disabled instead of deleted.');
        }

        $this->deleteUpload($paymentMethod->logo);
        $this->deleteUpload($paymentMethod->qr_image);
        $paymentMethod->delete();

        return back()->with('success', 'Payment method deleted.');
    }

    protected function form(PaymentMethod $method)
    {
        return view('admin.payment-methods.form', [
            'method' => $method,
            'drivers' => $this->gateways->options(),
            'driverFields' => collect($this->gateways->drivers())->map(fn ($class) => $class::configFields())->all(),
        ]);
    }

    protected function validated(Request $request, ?PaymentMethod $method = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('payment_methods', 'code')->ignore($method)],
            'type' => ['required', Rule::in(array_keys(PaymentMethod::types()))],
            'driver' => ['nullable', 'required_if:type,gateway', Rule::in(array_keys($this->gateways->drivers()))],
            'description' => 'nullable|string|max:255',
            'instructions' => 'nullable|string|max:3000',
            'details' => 'nullable|array|max:12',
            'details.*.label' => 'nullable|string|max:60',
            'details.*.value' => 'nullable|string|max:255',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'fee_amount' => 'nullable|numeric|min:0|max:100000',
            'advance_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'advance_value' => 'nullable|numeric|min:0|max:1000000',
            'min_order' => 'nullable|numeric|min:0',
            'max_order' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'config' => 'nullable|array',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], ['driver.required_if' => 'Choose which gateway driver this method uses.']);

        $data['code'] = Str::slug($data['code'] ?: $data['name'], '_');
        $data['details'] = collect($data['details'] ?? [])
            ->filter(fn ($row) => filled($row['label'] ?? null) && filled($row['value'] ?? null))
            ->map(fn ($row) => ['label' => trim($row['label']), 'value' => trim($row['value'])])
            ->values()->all();
        $data['discount_percent'] = (float) ($data['discount_percent'] ?? 0);
        $data['fee_amount'] = (float) ($data['fee_amount'] ?? 0);
        $data['advance_type'] = $data['type'] === PaymentMethod::TYPE_COD ? ($data['advance_type'] ?? 'none') : 'none';
        $data['advance_value'] = $data['advance_type'] === 'none' ? 0 : (float) ($data['advance_value'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['driver'] = $data['type'] === PaymentMethod::TYPE_GATEWAY ? $data['driver'] : null;
        $data['require_reference'] = $request->boolean('require_reference');
        $data['require_proof'] = $request->boolean('require_proof');
        $data['is_active'] = $request->boolean('is_active');

        // Keep stored secrets when a password field is left blank on edit.
        $config = [];
        foreach ($this->gateways->fieldsFor($data['driver']) as $key => $field) {
            $value = $data['config'][$key] ?? null;
            $config[$key] = ($field['type'] ?? 'text') === 'password' && blank($value) ? $method?->configValue($key) : $value;
        }
        $data['config'] = $config ?: null;

        $data['logo'] = $this->replaceUpload($request->file('logo'), $method?->logo, 'payments', $request->boolean('remove_logo'));
        $data['qr_image'] = $this->replaceUpload($request->file('qr_image'), $method?->qr_image, 'payments', $request->boolean('remove_qr'));

        return $data;
    }
}
