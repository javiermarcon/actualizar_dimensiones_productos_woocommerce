<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWAjaxHandlerUtilsTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testEnsureManageOptionsReturnsJsonErrorWhenUserCannotManageOptions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Ajax_Handler_Utils::ensure_manage_options('Sin permisos');
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Sin permisos', $e->payload['message']);
        }
    }

    public function testIdlePayloadReturnsExpectedShape(): void {
        self::assertSame([
            'status' => 'idle',
            'progress' => 0,
            'stage' => 'idle',
            'results' => null,
            'debug_log' => [],
        ], ADPW_Ajax_Handler_Utils::idle_payload());
    }

    public function testSuccessAndErrorSendJsonPayloads(): void {
        try {
            ADPW_Ajax_Handler_Utils::success(['ok' => true]);
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame(['ok' => true], $e->payload);
        }

        try {
            ADPW_Ajax_Handler_Utils::error(['error_general' => 'fallo']);
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame(['error_general' => 'fallo'], $e->payload);
        }
    }

    public function testHandleUnexpectedExceptionWrapsThrowableIntoJsonError(): void {
        try {
            ADPW_Ajax_Handler_Utils::handle_unexpected_exception(new RuntimeException('boom'), 'Prefijo: ');
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Prefijo: boom', $e->payload['error_general']);
        }
    }

    public function testHandleUnexpectedExceptionRethrowsCapturedJsonException(): void {
        $exception = new ADPW_Test_Json_Response_Exception(true, ['ok' => true]);

        $this->expectException(ADPW_Test_Json_Response_Exception::class);

        ADPW_Ajax_Handler_Utils::handle_unexpected_exception($exception, 'Prefijo: ');
    }
}
