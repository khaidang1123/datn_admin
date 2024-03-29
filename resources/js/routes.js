import AdminLayout from "./layouts/AdminLayout";
import Home from "./components/Admin/Home/HomeComponent";
import PayFist from "./components/Admin/PayFist/PayFistComponent"
import User from "./components/Admin/user/UserComponent"
import UserDetail from "./components/Admin/user/UserDetailComponent";
import Partner from "./components/Admin/Partner/PartnerComponent"
import Login from "./components/Auth/LoginComponent";
import ChineseMoney from "./components/Admin/ChineseMoney/ChineseMoneyComponent"
import ListMoneyVietNam from './components/Admin/VietNamese/ListMoneyVietNamComponent.vue';
import ListBag from './components/Admin/Bag/ListBagComponent.vue';
import AddBag from './components/Admin/Bag/AddBagComponent.vue';
import EditBag from './components/Admin/Bag/EditBagComponent.vue';
import Order from './components/Admin/Order/OrderComponent.vue';
import OrderDetail from './components/Admin/Order/OrderDetailComponent.vue';
import Package from './components/Admin/Order/PackageComponent.vue';
import OrderStatus from './components/Admin/Order/OrderStatusComponent.vue';
import Fee from './components/Admin/Order/FeeComponent.vue';
import ConfigPayment from './components/Admin/Setting/ConfigPaymentComponent.vue';
import DetailBag from './components/Admin/Bag/DetailBagComponent.vue'
import OrderEdit from './components/Admin/Order/OrderEditComponent.vue';
import ManagerComponent from './components/Admin/Manager/ManagerComponent.vue';
import Report from './components/Admin/Report/ReportComponent.vue';
import SettingFee from './components/Admin/SettingFee/FeeComponent.vue';
import SettingFeeEdit from './components/Admin/SettingFee/EditFeeComponent.vue';
import Noti from './components/Admin/Noti/NotiComponent.vue';

const routes = [{
        path: "/",
        component: AdminLayout,
        meta: {
            auth: true
        },
        children: [{
                path: "/",
                component: Home,
                meta: {
                    employee: true
                },
            },
            {
                path: "/home",
                component: Home,
                meta: {
                    employee: true
                },
            },
            {
                path: "pay-fist",
                component: PayFist,
                meta: {
                    just_superadmin: true
                },
            },
            {
                path: "user",
                component: User,
                meta: {
                    just_superadmin: true
                },
            },
            {
                path: "user-detail/:id",
                component: UserDetail
            },
            {
                path: "Chinese-money",
                component: ChineseMoney
            },
            {
                path: '/money-vietnamese',
                component: ListMoneyVietNam
            },
            {
                path: '/bag',
                component: ListBag,
                meta: {
                    employee: true
                },
            },
            {
                path: '/bag/add',
                component: AddBag,
                meta: {
                    employee: true
                },
            },
            {
                path: '/detail-bag/:id',
                component: DetailBag,
                meta: {
                    employee: true
                },
            },
            {
                path: '/bag/:id/edit',
                component: EditBag,
                meta: {
                    employee: true
                },
            },
            {
                path: '/order',
                component: Order,
                meta: {
                    employee: true
                },
            },
            {
                path: '/orderdetail/:id',
                component: OrderDetail,
                meta: {
                    employee: true
                },
            },
            {
                path: '/order/edit/:id',
                component: OrderEdit,
                meta: {
                    employee: true
                },
            },
            {
                path: '/orderdetail/package',
                component: Package
            },
            {
                path: '/orderdetail/fee',
                component: Fee
            },
            {
                path: '/orderdetail/status',
                component: OrderStatus
            },
            {
                path: '/partner',
                component: Partner
            },
            {
                path: '/manager',
                meta: {
                    just_superadmin: true
                },
                component: ManagerComponent
            },
            {
                path: '/config-payment',
                component: ConfigPayment
            },
            {
                path: '/report',
                component: Report,
                meta: {
                    just_superadmin: true
                }
            },
            {
                path: '/settings',
                component: SettingFee
            },
            {
                path: '/settings/edit',
                component: SettingFeeEdit
            },
            {
                path: '/settings/noti',
                component: Noti,
                meta: {
                    employee: true
                }
            }
        ],
    },
    {
        path: "/login",
        component: Login,
        meta: {
            notLogin: true
        }
    },

    { path: "/:catchAll(.*)", redirect: "/" },
    { "path": "/404", "name": "/" }

]
export default routes;