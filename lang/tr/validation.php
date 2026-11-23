<?php

// Sadece sık kullanılan kurallar; burada olmayan anahtarlar İngilizce (fallback) gösterilir.
return [
    'confirmed' => ':Attribute tekrarı eşleşmiyor.',
    'current_password' => 'Şifre hatalı.',
    'email' => ':Attribute geçerli bir e-posta adresi olmalı.',
    'lowercase' => ':Attribute küçük harflerden oluşmalı.',
    'max' => [
        'numeric' => ':Attribute en fazla :max olabilir.',
        'string' => ':Attribute en fazla :max karakter olabilir.',
    ],
    'min' => [
        'numeric' => ':Attribute en az :min olmalı.',
        'string' => ':Attribute en az :min karakter olmalı.',
    ],
    'required' => ':Attribute alanı zorunlu.',
    'string' => ':Attribute metin olmalı.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',

    'attributes' => [
        'name' => 'ad soyad',
        'email' => 'e-posta',
        'form.email' => 'e-posta',
        'password' => 'şifre',
        'form.password' => 'şifre',
        'password_confirmation' => 'şifre tekrarı',
        'current_password' => 'mevcut şifre',
    ],
];
