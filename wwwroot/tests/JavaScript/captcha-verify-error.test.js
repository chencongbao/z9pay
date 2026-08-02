const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

const file = `${process.cwd()}/public/vendor/captcha/js/verify.js`;
let source = fs.readFileSync(file, 'utf8');
source = source.replace(
    '})(jQuery, window, document);',
    '$.__captchaTest = { captchaRequestError, getPictrue, checkPictrue };\n})(jQuery, window, document);'
);

const storage = {};
global.localStorage = {
    getItem(key) {
        return storage[key] || null;
    },
    setItem(key, value) {
        storage[key] = value;
    },
};
global.document = { addEventListener() {}, all: false };
global.window = {};
global.Dcat = {
    lang: {
        captcha_fail: 'Verification failed',
        captcha_rate_limited: 'Please retry in :seconds seconds.',
        captcha_network_error: 'Network verification error',
    },
};

function jQuery() {
    return { css() {} };
}
jQuery.fn = {};
global.jQuery = jQuery;

vm.runInThisContext(source, { filename: file });

test('429 uses localized Retry-After message and ignores framework response body', () => {
    const error = jQuery.__captchaTest.captchaRequestError({
        status: 429,
        responseText: '429 Too Many Requests',
        getResponseHeader(name) {
            return name === 'Retry-After' ? '37' : null;
        },
    });

    assert.equal(error.repMsg, 'Please retry in 37 seconds.');
    assert.equal(error.repCode, '429');
    assert.equal(error.success, false);
    assert.doesNotMatch(error.repMsg, /Too Many Requests/);
});

test('network error uses localized generic message', () => {
    const error = jQuery.__captchaTest.captchaRequestError({ status: 0 });

    assert.equal(error.repMsg, 'Network verification error');
    assert.equal(error.repCode, 'NETWORK_ERROR');
});

test('missing reject callback is safe and normal success still resolves', () => {
    jQuery.ajax = (options) => options.error({ status: 429, getResponseHeader: () => '12' });
    assert.doesNotThrow(() => jQuery.__captchaTest.getPictrue({}, '/agent', () => {}));
    assert.doesNotThrow(() => jQuery.__captchaTest.checkPictrue({}, '/agent', () => {}));

    const result = { repCode: '0000', success: true };
    jQuery.ajax = (options) => options.success(result);
    let getResult;
    let checkResult;
    jQuery.__captchaTest.getPictrue({}, '/agent', (response) => {
        getResult = response;
    });
    jQuery.__captchaTest.checkPictrue({}, '/agent', (response) => {
        checkResult = response;
    });

    assert.equal(getResult, result);
    assert.equal(checkResult, result);
});
