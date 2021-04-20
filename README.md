# hack-shopping-mall TDD

## ⚙️ 개선 코드

- **기존 주문하기**
    - 기능만 동작하게 빠르게 구현함.
    - 객체들간의 상호작용을 신경안씀.
    - DB구조 복잡하게 생각.
    - 테스트, 리팩토링이 힘들고, 확장 거의 불가능.
<pre>
<code>
public function store(Product $product, Request $request)
{
    $request->validate([
        'selectIds' => 'required|array',
        'quantityList' => 'required|array',
    ]);

    $selectOptions = collect();
    $selectIds = $request->input('selectIds');
    $maxQuantityList = [];

    foreach ($selectIds as $i => $selectId) {
        $selectOptions->push(Code::find($selectId));
        $selectOptions[$i]->quantity = $request->input('quantityList')[$i];
        $maxQuantityList[] = $product->getCodeQuantity($selectOptions[$i]->period);
    }

    $total = $this->totalPrice($selectOptions);

    // 구매금액이 유저money 보다 크면 403반환
    if ($total > auth()->user()->money)
        return response()->json(['error' => '충전금이 부족합니다.'], 403);

    // 재고가 있는지 체크하는 함수
    $message = $this->inventoryManagement($selectOptions->pluck('quantity'), $maxQuantityList);
    if (isset($message['error']))
        return response()->json($message, 403);

    $hash = bin2hex(random_bytes(32));
    $order = $request->user()->orders()->create([
        'hash' => $hash,
        'title' => $product->title,
        'total' => $total,
        'file_link' => $product->file_link,
    ]);

    // 유저돈에서 total만큼 차감
    $money = $order->payment();

    // 구매개수랑 현재보유코드개수랑 같을경우 전체를 다 가져오고 아닐경우에는 Disabled = true만 가져온다. (false는 기준값)
    $codeList = [];
    foreach ($selectOptions as $i => $option) {
        if ($maxQuantityList[$i] == $option->quantity) {
            $codeList[$i] = $product->getCodeList($option->period, 0, $option->quantity);
        } else {
            $codeList[$i] = $product->getCodeList($option->period, 1, $option->quantity);
        }
        $order->codeList()->attach($codeList[$i]);
        foreach ($codeList[$i] as $code) {
            $code->delete();
        }
    }

    return response()->json([
        'message' => '결제 성공했습니다.',
        'money' => $money,
        'total' => $total,
    ], 200);
}
</code>
</pre>

- **현재 주문하기**
    - 테스트하기 쉬움
    - 기능추가 편함
    - 객체들 책임분배 명확
<pre>
<code>
class ProductsOrdersController extends Controller
{
    private $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function store(Product $product)
    {
        $this->validateRequest();

        try {
            // 코드 예약을 한다.
            $reservation = $product->reserveCodes(request('shopping_cart'), request('email'));
            // 해당 코드들에 대한 주문 생성 및 비용청구
            $order = $reservation->complete($this->paymentGateway, request('payment_token'));
            return response()->json(new OrderResource($order), 201);
        } catch (PaymentFailedException $e) {
            $reservation->cancel();
            return response()->json([], 422);
        } catch (NotEnoughCodesException $e) {
            return response()->json([], 422);
        }
    }

    protected function validateRequest()
    {
        $this->validate(request(), [
            'email' => ['required', 'email'],
            'payment_token' => ['required'],
            'shopping_cart' => ['required', 'array'],
            'shopping_cart.*.period' => ['required'],
            'shopping_cart.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }
}
</code>
</pre>

## ⚙️ 느낀점

* 아직은 테스트코드를 짜는 데 시간이 걸린다.
* 테스트를 어디까지 해야 할지 고민해봐야겠다.
* 좋은 코드를 짜기는 너무 어렵다.
* 라라벨 컬렉션 공부를 해야겠다.
* 나는 너무 형편없다.
* 열심히 해야겠다.
