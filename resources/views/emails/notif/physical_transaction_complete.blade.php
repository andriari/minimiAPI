<html>
    <head>
        <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" type="text/css">
        <style type="text/css">
            /* button check payment */
            .button-check-payment {
                background-color: #1fb2bc;
                color: white;
                font-family: 'Roboto';
                font-size: 14px;
                font-weight: bold;
                text-align: center;
                width: 100%;
                padding: 14px;
                text-decoration: none;
                box-sizing: border-box;
                box-shadow: 0 4px 5px 0 #eeeeee;
                border-radius: 2px;
                cursor: pointer;
            }
            /* button check payment */
        </style>
    </head>
    <body>
        <div style="max-width: 465px; font-family: 'Roboto'; margin:auto;">
            <!-- email content container -->
            <div style="background-color: white; padding: 35px 15px 60px;">
                <!-- minimi logo -->
                <div style="width: 160px; height: 48px; margin-bottom: 40px;">
                    <img src="{!! $logo !!}" alt="minimi" style="width: 100%;"/>
                </div>
                <!-- minimi logo -->

                <!-- email content -->
                <div>
                    <h1 style="margin: 0; font-family: 'Roboto'; font-size: 18px; font-weight: bold; color: #666666; margin-bottom: 12px;">Yeay barangmu sudah sampai!</h1>
                    <p style="margin: 0; font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; margin-bottom: 35px; line-height: 1.5;">Terimakasih sudah berbelanja di minimi, Berikan review agar pembeli lain mendapatkan inspirasi dalam berbelanja.</p>
                    <!-- detail product content -->
                    <table style="width:100%; margin-bottom: 20px;">
                        <tr>
                            <td>
                                <div style="margin-bottom: 20px;">
                                    <span style="font-family: 'Roboto'; font-size: 12px; font-weight: bold; color: #666666;">Detail Produk</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="width:100%;">
                                    @foreach ($items as $item)
                                    <tr>
                                        <td>
                                            <table style="align-items: center; margin-bottom: 15px; width:100%">
                                                <tr>
                                                    <td style="width:15%">
                                                        <img src="{!! $item->pict !!}" alt="{!! $item->alt !!}" style="width: 62px; height: 62px; background-color: lightblue; margin-right: 10px; object-position: center; object-fit: cover;" />
                                                    </td>
                                                    <td style="width:65%">
                                                        <div style="margin-right: 10px; font-family: 'Roboto'; font-size: 12px; color: #666666;">
                                                            <span style="display: block; font-weight: bold; margin-bottom: 4px;">{!! $item->brand_name !!}</span>
                                                            <span style="display: block; font-weight: normal; margin-bottom: 4px;">{!! $item->product_name !!} {!! $item->variant_name !!}</span>
                                                            <span style="display: block; font-weight: bold; font-size: 10px; color: #1fb2bc;">{!! $item->price_amount !!}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="font-family: 'Roboto'; font-size: 10px; color: #666666; text-align: right">{!! $item->count !!}x</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </table>
                    <!-- detail product content -->
                    <div style="box-sizing: border-box; border-top: 1px solid #d8d8d8; margin-bottom: 20px;"></div>
                    <!-- payment summary -->
                    <div style="background-color: white;">
                        <div style="font-family: 'Roboto'; font-size: 12px; font-weight: bold; color: #666666; margin-bottom: 10px;">Ringkasan Pembayaran</div>
                        <div style="margin-bottom: 8px;">
                            <table style="width:100%">
                                <tr>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Total Harga</div></td>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $price_amount !!}</div></td>
                                </tr>
                            </table>    
                        </div>
                        <div style="margin-bottom: 8px;">
                            <table style="width:100%">
                                <tr>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Total ongkos kirim</div></td>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $delivery_amount !!}</div></td>
                                </tr>
                            </table>    
                        </div>
                        <div style="margin-bottom: 8px;">
                            <table style="width:100%">
                                <tr>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Potongan Harga</div></td>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $discount_amount !!}</div></td>
                                </tr>
                            </table>    
                        </div>
                        <div style="margin-bottom: 8px;">
                            <table style="width:100%">
                                <tr>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Diskon Ongkos Kirim</div></td>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $delivery_discount_amount !!}</div></td>
                                </tr>
                            </table>    
                        </div>
                        <div style="margin-bottom: 8px;">
                            <table style="width:100%">
                                <tr>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Asuransi</div></td>
                                    <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $insurance_amount !!}</div></td>
                                </tr>
                            </table>    
                        </div>
                    </div>
                    <!-- payment summary -->
                    <div style="box-sizing: border-box; border-top: 1px solid #d8d8d8; margin-bottom: 10px;"></div>
                    <!-- total payment -->
                    <div style="margin-bottom: 55px;">
                        <table style="width:100%">
                            <tr>
                                <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666;">Total Pembayaran</div></td>
                                <td><div style="font-family: 'Roboto'; font-size: 14px; font-weight: normal; color: #666666; text-align:right;">{!! $total_amount !!}</div></td>
                            </tr>
                        </table>    
                    </div>

                    <!-- delivery detail -->
                    <div style="padding: 15px; margin: 0 -15px; background-color: #d8d8d8;">
                        <table style="width:100%">
                            <tr>
                                <td style="width:50%">
                                    <div style="font-family: 'Roboto'; font-size: 12px; font-weight: bold; color: #666666;">Detail Kurir</div>
                                </td>
                                <td>
                                    <div style="font-family: 'Roboto'; font-size: 12px; font-weight: bold; color: #666666;">Tujuan Pengiriman</div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="background-color: #f9f9f9; padding: 15px; margin: 0 -15px 40px;">
                        <table style="width:100%">
                            <tr>
                                <td style="width:50%">
                                    <table style="width:100%">
                                        <tr>
                                            <td>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 12px; font-weight: bold; margin-bottom: 8px;">Jasa Pengiriman</div>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 14px; font-weight: normal;">{!! $delivery_vendor !!}{!! $delivery_service !!}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td rowspan=3 valign="top">
                                    <div style="font-family: 'Roboto'; color: #666666;">
                                        <div style="font-size: 12px; font-weight: bold; margin-bottom: 8px;">Nama Penerima</div>
                                        <div style="font-size: 14px; font-weight: normal; margin-bottom: 8px; line-height: 1.3;">{!! $recipient_name !!}</div>
                                        <div style="font-size: 14px; font-weight: normal; margin-bottom: 8px; line-height: 1.3;">{!! $recipient_address !!}</div>
                                        <div style="font-size: 14px; font-weight: normal; margin-bottom: 8px; line-height: 1.3;">{!! $recipient_phone !!}</div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="width:100%">
                                        <tr>
                                            <td>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 12px; font-weight: bold; margin-bottom: 8px;">Nomor Resi</div>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 14px; font-weight: normal;">{!! $delivery_receipt_number !!}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="width:100%">
                                        <tr>
                                            <td>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 12px; font-weight: bold; margin-bottom: 8px;">Tanggal Terima</div>
                                                <div style="font-family: 'Roboto'; color: #666666; margin-bottom: 20px; font-size: 14px; font-weight: normal;">{!! $receive_date !!}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <!-- delivery detail -->

                    <!-- button check payment -->
                    <center>
                        <a href="{!! $link !!}" class="button-check-payment" style="color:white">Berikan Review</a>
                    </center>
                    <!-- button check payment -->
                </div>
                <!-- email content -->
            </div>
            <!-- email content container -->
            <!-- footer -->
            <div style="background-color: #666666; padding: 20px 15px 0px; color: white; font-family: 'Roboto'; font-size: 14px;">
                <span style="font-weight: bold; margin-bottom: 14px;">Anda butuh bantuan?</span>
                <p style="font-weight: normal; line-height: 1.5; width: 242px; margin-bottom: 15px;">Jangan ragu untuk menghubungi kami, kapanpun kami siap membantu Anda!</p>
                <span style="display: block; font-weight: normal; margin-bottom: 20px;">Hubungi kami melalui :</span>
                <div style="background-color: #4a4a4a; padding: 20px 15px; margin: 0 -15px;">
                    <div style="margin-bottom: 20px;">
                        <table style="width:100%">
                            <tr>
                                <td><img src="{!! $call_support !!}" alt="call_support" style="margin-right: 5%;" /></td>
                                <td style="width:90%"><div style="color: white; font-family: 'Roboto'; font-size: 14px;">0812 8753 255</div></td>
                            </tr>
                        </table>    
                    </div>
                    <div style="border-top: 1px solid #d8d8d8; margin-bottom: 20px;"></div>
                    <div style="margin-bottom: 20px;">
                        <table style="width:100%">
                            <tr>
                                <td><img src="{!! $whatsapp_support !!}" alt="whatsapp_support" style="margin-right: 5%;" /></td>
                                <td style="width:90%"><div style="color: white; font-family: 'Roboto'; font-size: 14px;">0812 8753 255</div></td>
                            </tr>
                        </table>    
                    </div>
                    <div style="box-sizing: border-box; border-top: 1px solid #d8d8d8; margin-bottom: 20px;"></div>
                    <div style="margin-bottom: 20px;">
                        <table style="width:100%">
                            <tr>
                                <td><img src="{!! $email_support !!}" alt="email_support" style="margin-right: 5%;" /></td>
                                <td style="width:90%">
                                    <a href='mailto:hello@minimi.co.id' style="color: white; font-family: 'Roboto'; font-size: 14px; text-decoration:none; cursor:pointer">hello@minimi.co.id</a>
                                </td>
                            </tr>
                        </table>    
                    </div>
                </div>
            </div>
            <!-- footer -->
        </div>
    </body>
</html>