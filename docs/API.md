# User Credits Extension — Tài liệu API

> Tài liệu kỹ thuật cho extension `user-credits` (namespace
> `Jankx\Extensions\UserCredits`). Bao gồm: PHP API, hooks, REST API, options,
> cấu trúc dữ liệu và các khối Gutenberg.

---

## 1. Tổng quan

Extension cung cấp hệ thống ví tín dụng (credit wallet) cho khách hàng:

- **Nhiều ví** (credit types) — mặc định là `coin` (meta: `user_credits_balance`).
- Nạp/rút/thưởng xu, lịch sử giao dịch (post type `jankx_credit_txn`).
- Thanh toán đơn hàng bằng xu qua `base-ecommerce` (`jankx/ecommerce/cart/discount`).
- **Tự động thưởng xu** khi đơn hàng được ghi nhận (`checkout/completed`),
  tỷ lệ & ví nhận xu cấu hình trong settings.
- Khối `jankx/account-tab-credits` hiển thị số dư + lịch sử trong My Account.

Entry point: `UserCreditsExtension` (auto_activate). Singleton `CreditManager`
là nơi truy cập chính (`CreditManager::instance()`).

---

## 2. PHP API

### 2.1 `CreditType\CreditManager` (singleton, factory chính)

| Method | Mô tả |
|---|---|
| `instance(): self` | Lấy instance dùng chung (registry + account mặc định). |
| `withRegistry(CreditTypeRegistryInterface $registry): self` | Tạo instance với registry tùy biến (thường dùng cho test). |
| `registerType(CreditType $type): void` | Đăng ký nhanh một loại ví. |
| `registry(): CreditTypeRegistryInterface` | Registry chứa các ví. |
| `account(): CreditAccount` | CreditAccount mặc định (balance user-meta + repository post type). |
| `defaultType(): CreditType` | Ví mặc định. |
| `boot(): void` | Bật registry; `trigger action jankx/user-credits/register_credit_types`. |

### 2.2 `Credit\CreditAccount`

| Method | Mô tả |
|---|---|
| `registry(): CreditTypeRegistryInterface` | |
| `resolveType(?string $typeId = null): CreditType` | Null/empty → ví mặc định. |
| `getBalance(int $userId, ?string $typeId = null): float` | Số dư hiện tại. |
| `setBalance(int $userId, float $balance, ?string $typeId = null): void` | Gán thẳng số dư (admin). |
| `deposit(int $userId, float $amount, string $note = '', ?string $typeId = null, string $title = ''): CreditTransaction` | Nạp (action `topup`). |
| `withdraw(int $userId, float $amount, string $note = '', ?string $typeId = null, string $title = ''): CreditTransaction` | Trừ (action `deduct`). |
| `transact(int $userId, string $action, float $amount, string $note = '', ?string $typeId = null, string $title = ''): CreditTransaction` | Ghi một giao dịch bất kỳ. |
| `getTransactions(int $userId, int $limit = 20, ?string $typeId = null): CreditTransaction[]` | Lịch sử gần nhất. |
| `countTransactions(int $userId, ?string $typeId = null): int` | Tổng số giao dịch. |

Ném `InvalidArgumentException` nếu user <= 0, amount <= 0 hoặc action lạ;
`InsufficientCreditBalanceException` nếu số dư không đủ (chỉ cho action trừ).

Ví dụ sử dụng:

```php
$account = \Jankx\Extensions\UserCredits\CreditType\CreditManager::instance()->account();

$balance = $account->getBalance($userId);
$txn = $account->deposit($userId, 100, 'Thưởng chương trình', 'coin', 'Nạp 100 xu');
$txn->getBalanceAfter(); // số dư mới
```

### 2.3 `CreditType\CreditType` (value object)

`DEFAULT_ID = 'coin'`, `LEGACY_META_KEY = 'user_credits_balance'`.

Constructor: `__construct(string $id, string $label = '', string $symbol = '', string $metaKey = '', int $decimals = 0, int $priority = 10)`

- `id` phải khớp `/^[a-z0-9][a-z0-9_-]*$/`, `decimals` trong `0..8`.
- Meta key mặc định: `coin` → `user_credits_balance`; ví khác → `jankx_credit_balance_{id}`.
- `CreditType::fromArray(array $config)` — tạo từ config.
- Getter: `getId/getLabel/getSymbol/getMetaKey/getDecimals/getPriority`, `is(string $id)`, `format(float $amount): string`, `toArray(): array`.

### 2.4 `Credit\CreditTransaction`

`toArray()`: `id, title, user_id, type, action, action_label, wallet, amount, signed_amount, balance_after, note, date`.
Getter: `getId/getUserId/getWalletId/getAction/getAmount/getBalanceAfter/getNote/getDate/getTitle/isCredit/getSignedAmount/getActionLabel`.

### 2.5 `Credit\CreditTransactionAction`

Action: `topup`, `deduct`, `refund`, `booking`, `commission`, `reward`.
- `all(): string[]` — danh sách đầy đủ.
- `credits(): string[]` — action **tăng** số dư: `topup`, `refund`, `commission`, `reward`.
- `isCredit(string $action): bool`, `exists(string $action): bool`, `label(string $action): string`.

### 2.6 `CreditType\CreditTypeRegistryInterface` / `CreditType\CreditTypeRegistry`

- `register(CreditType)`, `registerMany(iterable)`, `has(string $id): bool`, `get(string $id): CreditType`, `getDefault(): CreditType`, `setDefault(string $id)`, `all(): CreditType[]`, `ids(): string[]`.
- Lỗi: `CreditTypeAlreadyRegisteredException`, `CreditTypeNotFoundException`.

### 2.7 `Integration\CheckoutIntegration` (singleton — thanh toán bằng xu)

| Method | Mô tả |
|---|---|
| `get_instance(?CreditAccount $account = null): self` | Singleton. |
| `isEnabled(): bool` | Option `jankx_credit_payment_enabled`. |
| `getLabel(): string` | Option `jankx_credit_payment_label` (mặc định "Dùng tín dụng để thanh toán"). |
| `isApplied(): bool` | Xu đang được áp dụng cho giỏ hàng hiện tại (theo transient theo cart key). |
| `apply(bool $use): array` | Bật/tắt áp xu cho giỏ; trả `{success, message, applied?}`. |
| `removeApplied(): array` | Gỡ áp xu. |
| `getBalance(int $userId = 0): float` | Số dư user (mặc định user đang đăng nhập). |
| `getAppliedCreditDiscount($cart): float` | Số giảm do xu (tách riêng với coupon). |
| `calculateCreditDiscount($cart, float $currentDiscount = 0.0): float` | Như trên, tính thuần xu. |
| `getSessionKey(): string` | `jankx_credit_payment_{cartKey}`. |

Tỷ lệ dùng khi thanh toán: **1 xu = 1 đơn vị tiền tệ mặc định** (VND).

### 2.8 `Reward\OrderRewardIntegration` (singleton — thưởng xu khi hoàn thành đơn)

| Method | Mô tả |
|---|---|
| `get_instance(?CreditAccount $account = null): self` | Singleton. |
| `isEnabled(): bool` | Option `jankx_credit_reward_enabled`. |
| `getAmountPerCredit(): float` | Option `jankx_credit_reward_amount_per_credit` (mặc định 10.000đ = 1 xu). |
| `getMinOrderTotal(): float` | Option `jankx_credit_reward_min_order_total` (mặc định 0). |
| `getWalletId(): string` | Option `jankx_credit_reward_wallet` — ví nhận xu; empty = ví mặc định. |
| `calculateCredits(float $orderTotal, int $userId = 0): float` | `floor(total / per_credit)`; override qua filter. |
| `onCheckoutCompleted($order): void` | Handler trên `jankx/ecommerce/checkout/completed` (priority 30). |
| `alreadyAwarded(int $userId, int $orderId): bool` | Kiểm tra đã thưởng chưa. |

### 2.9 Storage / Repository / Interfaces

- `Credit\CreditBalanceStoreInterface` — `get(int $userId, CreditType $type): float`, `set(...): void`.
- `Credit\UserMetaCreditBalanceStore` — implement dựa trên user meta.
- `Credit\CreditTransactionRepositoryInterface` — `record(...)`, `findByUser(...)`, `countByUser(...)`.
- `Credit\PostTypeCreditTransactionRepository` — ghi giao dịch thành post `jankx_credit_txn`; hằng meta:
  `META_ACTION=_credit_type`, `META_WALLET=_credit_wallet`, `META_AMOUNT=_credit_amount`,
  `META_BALANCE_AFTER=_credit_balance_after`, `META_USER=_credit_user_id`, `META_NOTE=_credit_note`.

### 2.10 Ngoại lệ

- `Credit\InsufficientCreditBalanceException` — `getType(): CreditType`, `getBalance()`, `getRequested()`.
- `CreditType\CreditTypeNotFoundException` (RuntimeException).
- `CreditType\CreditTypeAlreadyRegisteredException`.

---

## 3. Hooks

### 3.1 Actions

| Hook | Đối số | Khi nào | Mục đích |
|---|---|---|---|
| `jankx/user-credits/register_credit_types` | `$registry` | `CreditManager::boot()` bật registry | **Đăng ký ví mới** từ extension khác. |
| `jankx/user-credits/transacted` | `$transaction, $type` | Sau khi ghi mỗi giao dịch | Log/notification. |
| `jankx/user-credits/balance-changed` | `$userId, $type, $newBalance, $oldBalance` | Sau khi cân bằng đổi | Đồng bộ cảnh báo. |
| `jankx/user-credits/reward_awarded` | `$order, $credits, $userId, $balanceAfter` | Sau khi thưởng xu đơn | Thông báo khách. |
| `jankx/ecommerce/credits/applied` | `$userId` | Khách bật dùng xu cho giỏ | Analytics/UI. |
| `jankx/ecommerce/credits/removed` | `$userId` | Khách tắt dùng xu | Analytics/UI. |
| `jankx/ecommerce/credits/used` | `$order, $deduct, $userId, $balanceAfter` | Sau khi trừ xu tại checkout completed | Ghi nhận. |
| `jankx/my_account/register_sub_pages` | (không đối số cụ thể) | Extension tự đăng ký sub-page `credits` | My Account. |

### 3.2 Filters

| Hook | Kiểu | Đối số | Mô tả |
|---|---|---|---|
| `jankx/ecommerce/cart/discount` | float (priority 20) | `$discount, $cart` | Cộng phần giảm do dùng xu vào discount giỏ hàng. |
| `jankx/user-credits/reward_calculate` | float | `$credits, $orderTotal, $userId` | Override số xu thưởng cho một đơn. |

### 3.3 Đăng ký một ví mới (ví dụ)

```php
add_action('jankx/user-credits/register_credit_types', function ($registry) {
    $registry->register(\Jankx\Extensions\UserCredits\CreditType\CreditType::fromArray([
        'id'        => 'bonus',
        'label'     => 'Xu ưu đãi',
        'symbol'    => 'xu',
        'decimals'  => 0,
        'priority'  => 10,
    ]));
});
```

Sau đó ví `bonus` xuất hiện trong settings, admin profile, REST `/credits/types`,
và có thể được chọn là "Ví nhận xu" trong phần thưởng xu.

---

## 4. REST API — namespace `jankx/v1`

Nonce: dùng `wp_rest` nonce cho các route cần đăng nhập.
Header: `X-WP-Nonce: <nonce>`.

### 4.1 `GET /credits/types` — công khai

Tra danh sách ví. Trả về:

```json
{
  "success": true,
  "types": [{ "id": "coin", "label": "Coin", "symbol": "coin", "meta_key": "user_credits_balance", "decimals": 0, "priority": 10 }],
  "default": "coin"
}
```

### 4.2 `GET /credits/balance` — user đã đăng nhập

Query: `type` (optional, mặc định ví mặc định).

```json
{ "success": true, "user_id": 3, "type": "coin", "label": "Coin", "balance": 1200, "currency": "coin", "formatted": "1.200 coin" }
```

### 4.3 `GET /credits/history` — user đã đăng nhập

Query: `user_id` (optional; chỉ admin xem được user khác), `type`, `per_page` (default 20).

```json
{ "success": true, "type": null, "transactions": [ { "id": 1, "title": "...", "user_id": 3, "type": "reward", "action": "reward", "action_label": "Xu thưởng", "wallet": "coin", "amount": 2, "signed_amount": 2, "balance_after": 1200, "note": "Đơn hàng OD-000001", "date": "..." } ], "total": 4 }
```

### 4.4 `POST /credits/add` — admin (`manage_options`)

Body: `user_id` (bắt buộc), `amount` (> 0, bắt buộc), `type` (optional), `note` (optional).
Trừ lỗi: 404 user không tồn tại, 400 tham số không hợp lệ (gồm `InvalidArgumentException`).

```json
{ "success": true, "transaction_id": 12, "type": "coin", "balance": 1200, "added": 100, "message": "Đã nạp 100 coin cho ..." }
```

### 4.5 `POST /credits/deduct` — admin

Body như `/credits/add`. Lỗi `InsufficientCreditBalanceException` → 400 với message kèm số dư hiện tại.

```json
{ "success": true, "transaction_id": 13, "type": "coin", "balance": 1100, "deducted": 100, "message": "Đã trừ 100 coin từ ..." }
```

### 4.6 `POST /credits/cart/apply` — user đã đăng nhập

Body: `use` (bool, default true — bật/tắt dùng xu cho giỏ hàng hiện tại).
Trả về trạng thái áp dụng + giỏ hàng:

```json
{ "success": true, "message": "...", "applied": true, "type": "coin", "balance": 1100, "credit_discount": 500000, "coupon_discount": 0, "formatted_credit_discount": "500.000đ", "formatted_coupon_discount": "", "cart": { ... } }
```

### 4.7 `POST /credits/cart/remove` — user đã đăng nhập

Gỡ áp dụng xu khỏi giỏ. Response tương tự `apply` với `applied: false`.

Không tồn tại route "thưởng xu" riêng — thưởng tự động do handler
`OrderRewardIntegration` chạy tại `jankx/ecommerce/checkout/completed`.

---

## 5. Options (cài đặt)

Nhóm settings: `jankx_credit_settings` (màn hình **Cài đặt tín dụng**,
`admin.php?page=jankx-credit-settings`). Đọc qua
`Jankx\Adapter\Options\Helper` (fallback `get_option`).

### 5.1 Ví / nạp tiền

| Option | Default | Mô tả |
|---|---|---|
| `jankx_credit_enabled` | `yes` | Bật hệ thống tín dụng. |
| `jankx_credit_currency_symbol` | `coin` | Ký hiệu ví mặc định. |
| `jankx_credit_min_topup` | `10000` | Nạp tối thiểu (VND). |
| `jankx_credit_max_topup` | `50000000` | Nạp tối đa (VND). |
| `jankx_credit_expiry_days` | `0` | Hạn sử dụng xu (0 = không hết hạn; chưa sink vào luồng balance). |

### 5.2 Thanh toán bằng xu (Theme Options, page `user_credits`)

| Option | Default | Mô tả |
|---|---|---|
| `jankx_credit_payment_enabled` | `1` | Cho phép khách trả bằng xu (1 xu = 1đ). |
| `jankx_credit_payment_label` | `''` | Nhãn hiển thị tại cart/checkout. |

### 5.3 Thưởng xu khi hoàn thành đơn hàng

| Option | Default | Mô tả |
|---|---|---|
| `jankx_credit_reward_enabled` | `no` | Bật tự động thưởng xu. |
| `jankx_credit_reward_amount_per_credit` | `10000` | Số VND tương đương 1 xu. |
| `jankx_credit_reward_min_order_total` | `0` | Đơn tối thiểu mới được thưởng. |
| `jankx_credit_reward_wallet` | `''` | Ví nhận xu; trống = ví mặc định. |

---

## 6. Cấu trúc dữ liệu

| Thành phần | Chi tiết |
|---|---|
| Post type giao dịch | `jankx_credit_txn` (`show_ui`, không `create_posts` — chỉ tạo qua `CreditAccount`). |
| Meta giao dịch | `_credit_type`, `_credit_wallet`, `_credit_amount`, `_credit_balance_after`, `_credit_user_id`, `_credit_note`. |
| Balance user meta | ví `coin` → `user_credits_balance`; ví khác → `jankx_credit_balance_{id}`. |
| Đánh dấu đã thưởng | user meta `_jankx_credit_reward_order_{orderId}`. |
| Session áp dụng xu | transient `jankx_credit_payment_{cartKey}` (+ `_amount`), TTL 30 ngày. |

---

## 7. Gutenberg & My Account

- Block: **`jankx/account-tab-credits`** (parent `jankx/account-content`) — hiển thị ví mặc định, số dư, lịch sử 20 giao dịch gần nhất.
- Sub-page My Account: **`credits`** (đăng ký qua `jankx/my_account/register_sub_pages`).
- Assets khi có giỏ/checkout + khách đăng nhập + đủ xu:
  `credits-payment.js` / `credits-payment.css`, biến `jankxCreditsPayment = { restUrl, nonce, i18n }`.

---

## 8. Luồng nghiệp vụ liên quan

1. **Thanh toán bằng xu**: khách `POST /credits/cart/apply {use:true}` → filter
   `jankx/ecommerce/cart/discount` cộng phần giảm → tại `checkout/completed`
   (priority 20) `CheckoutIntegration` trừ xu và ghi txn `deduct`.
2. **Thưởng xu**: tại `checkout/completed` (priority 30) `OrderRewardIntegration`
   tính `floor(total/per_credit)`, ghi txn `reward` vào ví cấu hình, đánh dấu
   `_jankx_credit_reward_order_{orderId}` để chống trùng.

Order ưu tiên tại `checkout/completed`: `CheckoutIntegration` (20) → membership
→ `OrderRewardIntegration` (30).