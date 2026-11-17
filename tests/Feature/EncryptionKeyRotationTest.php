<?php

namespace Tests\Feature;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptionKeyRotationTest extends TestCase
{
    public function test_values_encrypted_with_a_previous_key_remain_decryptable_after_rotation(): void
    {
        $previousKey = random_bytes(32);
        $currentKey = random_bytes(32);
        $previousKeyValue = 'base64:'.base64_encode($previousKey);
        $currentKeyValue = 'base64:'.base64_encode($currentKey);
        $cipher = config('app.cipher');
        $legacyPayload = (new Encrypter($previousKey, $cipher))->encryptString('preserved application value');

        config([
            'app.key' => $currentKeyValue,
            'app.previous_keys' => [$previousKeyValue],
        ]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $this->assertSame('preserved application value', Crypt::decryptString($legacyPayload));

        $newPayload = Crypt::encryptString('new application value');
        $this->assertSame('new application value', Crypt::decryptString($newPayload));
    }
}
