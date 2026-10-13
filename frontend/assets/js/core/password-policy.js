// [E-08] 使用 Unicode 字元數，與 PHP mb_strlen 一致；不修剪密碼。
export function passwordError(password) {
  const length = Array.from(password).length;
  return length < 15 || length > 128 ? '密碼須為 15–128 個字元，可使用空白與 Unicode。' : '';
}

// [U-15] 確認密碼必填，保留空白及 Unicode 的原始內容比較。
export function passwordConfirmationError(password, confirmation) {
  if (confirmation === '') return '請再次輸入密碼。';
  return password === confirmation ? '' : '兩次密碼不一致。';
}
