// 以 Unicode 字元數計算長度，與 PHP 的 mb_strlen 一致；密碼不做 trim。
export function passwordError(password) {
  const length = Array.from(password).length;
  return length < 8 || length > 128 ? '密碼須為 8–128 個字元，可使用空白與 Unicode。' : '';
}

// 確認密碼為必填，比較時保留原始內容（包括空白與 Unicode 字元）。
export function passwordConfirmationError(password, confirmation) {
  if (confirmation === '') return '請再次輸入密碼。';
  return password === confirmation ? '' : '兩次密碼不一致。';
}
