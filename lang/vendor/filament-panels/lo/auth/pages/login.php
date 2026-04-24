<?php

return [
    'title' => 'ເຂົ້າລະບົບ',

    'heading' => 'ເຂົ້າລະບົບ',

    'actions' => [
        'register' => [
            'before' => 'ຫຼື',
            'label' => 'ລົງທະບຽນບັນຊີ',
        ],

        'request_password_reset' => [
            'label' => 'ລືມລະຫັດຜ່ານ?',
        ],
    ],

    'form' => [
        'email' => [
            'label' => 'ອີເມວ',
        ],

        'password' => [
            'label' => 'ລະຫັດຜ່ານ',
        ],

        'remember' => [
            'label' => 'ຈື່ການເຂົ້າລະບົບ',
        ],

        'actions' => [
            'authenticate' => [
                'label' => 'ເຂົ້າລະບົບ',
            ],
        ],
    ],

    'multi_factor' => [
        'heading' => 'ຢືນຢັນຕົວຕົນ',

        'subheading' => 'ເພື່ອເຂົ້າລະບົບຕໍ່ ທ່ານຕ້ອງຢືນຢັນຕົວຕົນ.',

        'form' => [
            'provider' => [
                'label' => 'ທ່ານຕ້ອງການຢືນຢັນດ້ວຍວິທີໃດ?',
            ],

            'actions' => [
                'authenticate' => [
                    'label' => 'ຢືນຢັນການເຂົ້າລະບົບ',
                ],
            ],
        ],
    ],

    'messages' => [
        'failed' => 'ຂໍ້ມູນເຂົ້າລະບົບບໍ່ຖືກຕ້ອງ.',
    ],

    'notifications' => [
        'throttled' => [
            'title' => 'ພະຍາຍາມເຂົ້າລະບົບຫຼາຍເກີນໄປ',
            'body' => 'ກະລຸນາລອງໃໝ່ໃນ :seconds ວິນາທີ.',
        ],
    ],
];
