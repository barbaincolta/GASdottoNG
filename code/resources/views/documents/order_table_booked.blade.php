@include('documents.order_table_master', [
    'selected_bookings' => $order->bookings()->toplevel()->get(),
    'get_function' => 'getBookedQuantity',
    'get_total' => 'value_with_friends',
    'get_function_real' => true
])
