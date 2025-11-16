<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class StaffListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理_スタッフ一覧に氏名とメールが表示される(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->createOne(['role' => 'admin']); // 単一モデルを明示
        $this->actingAs($admin);

        /** @var \App\Models\User $u1 */
        $u1 = User::factory()->createOne([
            'role'  => 'user',
            'name'  => '佐藤一郎',
            'email' => 'sato@example.com',
        ]);

        /** @var \App\Models\User $u2 */
        $u2 = User::factory()->createOne([
            'role'  => 'user',
            'name'  => '鈴木花子',
            'email' => 'suzuki@example.com',
        ]);

        $res = $this->get(route('admin.staff.index'));
        $res->assertOk();

        $html = $res->getContent();
        $this->assertStringContainsString($u1->name,  $html);
        $this->assertStringContainsString($u1->email, $html);
        $this->assertStringContainsString($u2->name,  $html);
        $this->assertStringContainsString($u2->email, $html);
    }
}