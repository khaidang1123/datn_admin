<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Packet\AdminPacketResource;
use App\Http\Resources\Packet\PacketListResource;
use App\Http\Resources\Packet\PacketOrderResource;
use App\Models\AdminPacketItemModel;
use App\Models\AdminPacketModel;
use App\Models\OrderModel;
use App\Models\PacketModel;
use App\Models\WareHouseModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class PacketController extends Controller
{
    protected $wareHouseModel;
    protected $packetModel;
    protected $adminPacketItemModel;


    public function __construct(
        WareHouseModel $wareHouseModel,
        PacketModel $packetModel,
        AdminPacketItemModel $adminPacketItemModel,
        OrderModel $orderModel
    ) {
        $this->wareHouseModel = $wareHouseModel;
        $this->packetModel = $packetModel;
        $this->adminPacketItemModel = $adminPacketItemModel;
        $this->orderModel = $orderModel;
    }

    public function getPacket(Request $request)
    {
        try {
            $is_wood_packing = $request->is_wood_packing ? true : false;
            $is_paid = $request->is_paid ? true : false;

            $packets = DB::table('admin_packets')
                ->leftJoin('admin_packet_items', 'admin_packet_items.admin_packet_id', 'admin_packets.id')
                ->select(
                    'admin_packets.code',
                    'admin_packets.wood_packing',
                    'admin_packets.note',
                    'admin_packets.total_price',
                    'admin_packets.status_id',
                    'admin_packets.tracking_status_name',
                    'admin_packets.id',
                    'admin_packets.warehouse_id',
                    'admin_packets.paid',
                    'admin_packets.fee_service',
                )
                ->orderByDesc('admin_packets.created_at');
            if ($request->code) {
                $packets->where('admin_packets.code', 'like', "%{$request->code}%");
            }
            if ($request->order_id) {
                $regex_id = preg_replace('/^0*/', '', $request->order_id);
                $packets->where('admin_packet_items.order_id', 'like', "%{$regex_id}%");
            }
            if ($request->waybill_code) {
                $packets->where('admin_packet_items.waybill_code', 'like', "%{$request->waybill_code}%");
            }
            if ($request->from) {
                $packets->whereDate('admin_packets.created_at', '>=', $request->from);
            }
            if ($request->to) {
                $packets->whereDate('admin_packets.created_at', '<=', $request->to);
            }
            if ($request->is_wood_packing != null) {
                $packets->where('admin_packets.wood_packing', $is_wood_packing);
            }
            if ($request->is_paid != null) {
                $packets->where('admin_packets.paid', $is_paid);
            }
            $packets->groupBy('admin_packets.code',
            'admin_packets.wood_packing',
            'admin_packets.note',
            'admin_packets.total_price',
            'admin_packets.status_id',
            'admin_packets.tracking_status_name',
            'admin_packets.id',
            'admin_packets.warehouse_id',
            'admin_packets.paid',
            'admin_packets.fee_service',);
            $packets = $packets->paginate(config('const.pagination.per_page'));
            return PacketListResource::collection($packets);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function searchOrder(Request $request)
    {
        try {
            $code = $request->code;
            $order = DB::table('packets')
                ->where('packets.code', $code)
                ->select(
                    'order_id',
                    'code',
                )
                ->whereNotIn('order_id', function ($query) {
                    $query->select('order_id')
                        ->from('admin_packet_items');
                })
                ->where('is_delete', false)
                ->first();
            return [
                'order' => $order ? new PacketOrderResource($order) : [
                    'code' => $request->code,
                    'order_id' => ''
                ]
            ];
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function transportStatus()
    {
        try {
            return response()->json([
                'admin_packet_status' => config('const.admin_packet_status')
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function store(Request $request)
    {
        $wood_packing = $request->wood_packing ? 1 : 0;
        $paid = $request->paid ? 1 : 0;

        $validator = Validator::make(
            $request->all(),
            [
                'warehouse_id' => 'required',
                'weight' => 'required|numeric',
                'volume' => 'required|numeric',
                'weight_from_volume' => 'required|numeric',
                'unit_price' => 'required|numeric',
                'wood_packing_price' => 'required|numeric',
                'orther_price' => 'numeric'
            ],
            [
                'warehouse_id.required' => 'Vui lòng chọn kho hàng!',
                'weight.required' => 'Vui lòng nhập khối lượng bao hàng!',
                'weight.numeric' => 'Khối lượng phải là số',
                'volume.required' => 'Vui lòng nhập thể tích bao hàng!',
                'volume.numeric' => 'Thể tích phải là số',
                'weight_from_volume.required' => 'Vui lòng nhập khối lượng quy đổi!',
                'weight_from_volume.numeric' => 'Khối lượng quy đổi phải là số',
                'unit_price.required' => 'Vui lòng nhập đơn giá!',
                'unit_price.numeric' => 'Đơn giá phải là số!',
                'wood_packing_price.required' => 'Vui lòng nhập phí đóng gỗ!',
                'wood_packing_price.numeric' => 'Phí đóng gỗ phải là số!',
                'orther_price.numeric' => 'Phí khác phải là số!'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        // check ware_house id
        $ware_id = $request->warehouse_id ?? 0;

        if ($ware_id && !$this->wareHouseModel->checkWareIsset($ware_id)) {
            return response()->json([
                'error' => true,
                'message' => 'Không tồn tại kho hàng'
            ]);
        }

        $order_items = $request->order_valid ?? null;
        if (!$order_items) {
            return response()->json(['order_null' => 'Vui lòng thêm đơn hàng vào bao hàng!']);
        } else {
            // Check order
            foreach ($order_items as $value) {
                if (!$this->packetModel->checkPacket($value['order_id'], $value['code'])) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Không tồn tại đơn hàng có mã vận đơn ' . $value['code']
                    ]);
                }

                if ($this->adminPacketItemModel->checkPacketIsset($value['order_id'], $value['code'])) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Đơn hàng có mã vận đơn ' . $value['code'] . ' đã tồn tại trong bao hàng vui lòng kiểm tra lại'
                    ]);
                }
            }
        }

        try {
            $code = $request->code;
            $data_admin_packet = [
                'fee_service' => $request->fee_service,
                'weight' => $request->weight,
                'volume' => $request->volume,
                'weight_from_volume' => $request->weight_from_volume,
                'wood_packing' => $wood_packing,
                'note' => $request->note,
                'status_id' => $request->status_id,
                'tracking_status_name' => $request->tracking_status_name,
                'unit_price' => $request->unit_price,
                'wood_packing_price' => $request->wood_packing_price,
                'other_price' => $request->other_price,
                'paid' => $paid,
                'warehouse_id' => $request->warehouse_id,
                'code' => $code
            ];
            $new_packet = AdminPacketModel::create($data_admin_packet);
            foreach ($order_items as $value) {
                $data_admin_packet_item = [
                    'order_id' => $value['order_id'],
                    'admin_packet_id' => $new_packet->id,
                    'waybill_code' => $value['code']
                ];
                $packetNew = AdminPacketItemModel::create($data_admin_packet_item);
                $this->orderModel->updateStatusOrderWithPacket($value['order_id'], $request->status_id);
                DB::table('tracking_statuses')->insert([
                    'order_id' => $value['order_id'],
                    'name' => "SSG1",
                    'tracking_status_name' => "Chờ xác nhận (China)",
                    'created_at' => Carbon::now('Asia/Ho_Chi_Minh')
                ]);
            }
            return response()->json(['success' => 'Tạo bao hàng thành công']);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => 'Có lỗi xảy ra, vui lòng liên hệ quản trị viên!'
            ]);
        }
    }

    public function show($id)
    {
        try {
            $admin_packet = AdminPacketModel::where('id', $id)
                ->select(
                    'warehouse_id',
                    'weight',
                    'volume',
                    'code',
                    'weight_from_volume',
                    'unit_price',
                    'wood_packing_price',
                    'other_price',
                    'wood_packing',
                    'paid',
                    'status_id',
                    'note',
                    'tracking_status_name'
                )
                ->where('is_delete', false)
                ->first();

            if (!$admin_packet) {
                return response()->json([
                    'error' => true,
                    "message" => 'id không tồn tại'
                ]);
            }
            $admin_packet_items = AdminPacketItemModel::where('admin_packet_id', $id)
                ->select(
                    'order_id',
                    'waybill_code',
                    'id'
                )
                ->where('is_delete', false)
                ->get();

            return response()->json([
                'admin_packet' => new AdminPacketResource($admin_packet),
                'admin_packet_items' => PacketOrderResource::collection($admin_packet_items)
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if($request->weight_from_volume > 0 && $request->weight_from_volume <=5){
                $fee_service = 40000;
            }
            else if($request->weight_from_volume > 5 && $request->weight_from_volume <=20){
                $fee_service = 70000;
            }
            else if($request->weight_from_volume > 20 && $request->weight_from_volume <=100){
                $fee_service = 100000;
            }
            else if($request->weight_from_volume > 100){
                $fee_service = 200000;
            }
            if (!AdminPacketModel::find($id)) {
                return response()->json([
                    'error' => true,
                    "message" => 'id không tồn tại'
                ]);
            }
            $wood_packing = $request->wood_packing ? 1 : 0;
            $paid = $request->paid ? 1 : 0;

            $validator = Validator::make(
                $request->all(),
                [
                    'warehouse_id' => 'required',
                    'weight' => 'required|numeric',
                    'volume' => 'required|numeric',
                    'weight_from_volume' => 'required|numeric',
                    'unit_price' => 'required|numeric',
                    'wood_packing_price' => 'required|numeric',
                    'orther_price' => 'numeric'
                ],
                [
                    'warehouse_id.required' => 'Vui lòng chọn kho hàng!',
                    'weight.required' => 'Vui lòng nhập khối lượng bao hàng!',
                    'weight.numeric' => 'Khối lượng phải là số',
                    'volume.required' => 'Vui lòng nhập thể tích bao hàng!',
                    'volume.numeric' => 'Thể tích phải là số',
                    'weight_from_volume.required' => 'Vui lòng nhập khối lượng quy đổi!',
                    'weight_from_volume.numeric' => 'Khối lượng quy đổi phải là số',
                    'unit_price.required' => 'Vui lòng nhập đơn giá!',
                    'unit_price.numeric' => 'Đơn giá phải là số!',
                    'wood_packing_price.required' => 'Vui lòng nhập phí đóng gỗ!',
                    'wood_packing_price.numeric' => 'Phí đóng gỗ phải là số!',
                    'orther_price.numeric' => 'Phí khác phải là số!'
                ]
            );

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()]);
            }

            // check ware_house id
            $ware_id = $request->warehouse_id ?? 0;

            if ($ware_id && !$this->wareHouseModel->checkWareIsset($ware_id)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Không tồn tại kho hàng'
                ]);
            }

            if (!$request->order_valid) {
                return response()->json(['order_null' => 'Vui lòng thêm đơn hàng vào bao hàng!']);
            }
            $data_admin_packet = [
                'weight' => $request->weight,
                'volume' => $request->volume,
                'weight_from_volume' => $request->weight_from_volume,
                'wood_packing' => $wood_packing,
                'note' => $request->note,
                'status_id' => $request->status_id,
                'unit_price' => $request->unit_price,
                'wood_packing_price' => $request->wood_packing_price,
                'other_price' => $request->other_price,
                'paid' => $paid,
                'warehouse_id' => $request->warehouse_id,
                'fee_service' => $fee_service
            ];
            AdminPacketModel::find($id)
                ->update($data_admin_packet);
            foreach ($request->order_valid as $value) {
                if (!$value['id']) {
                    $data_admin_packet_item = [
                        'order_id' => $value['order_id'],
                        'admin_packet_id' => $id,
                        'waybill_code' => $value['code']
                    ];
                    AdminPacketItemModel::create($data_admin_packet_item);
                }
                $this->orderModel->updateStatusOrderWithPacket($value['order_id'], $request->status_id);
            }
            return response()->json(['success' => 'Chỉnh sửa bao hàng thành công']);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function destroy($id)
    {
        try {
            if (!AdminPacketModel::find($id)) {
                return response()->json([
                    'error' => true,
                    "message" => 'id không xác định'
                ]);
            }
            AdminPacketModel::where('id', $id)->update(['is_delete' => 1]);
            AdminPacketItemModel::where('admin_packet_id', $id)->update(['is_delete' => 1]);

            return response()->json([
                "message" => "Xoá bao hàng thành công"
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                "message" => $th->getMessage()
            ]);
        }
    }

    public function showDetailBag(Request $request)
    {
        $data = DB::table('admin_packet_items')
            ->join('admin_packets', 'admin_packets.id', '=', 'admin_packet_items.admin_packet_id')
            ->join('orders', 'orders.id', '=', 'admin_packet_items.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select(
                'admin_packet_items.order_id',
                'users.username',
                'orders.order_code',
                'orders.total_price',
                'orders.created_at',
                'orders.purchase_fee',
                'orders.inventory_fee',
                'orders.total_price_order',
                'orders.global_shipping_fee',
                'orders.china_shipping_fee',
                'orders.wood_packing_fee',
                'orders.separately_wood_packing_fee',
                'orders.high_value_fee',
                'orders.auto_shipping_fee',
                'orders.saving_shipping_fee',
                'orders.express_shipping_fee',
            )
            ->where('admin_packet_items.admin_packet_id', '=', $request->packets_id)
            ->paginate(10);
        return $data;
    }

    public function getStatusTrackingBag(Request $request)
    {
        $data = DB::table('admin_packets')
            ->select(
                'id',
                'code',
                'tracking_status_name'
            )
            ->where('id', $request->bag_id)->first();
        return response()->json($data);
    }
}
