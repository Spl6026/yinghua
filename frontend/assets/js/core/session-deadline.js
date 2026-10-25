// [E-29] 使用相對的單調時鐘；不受使用者電腦時鐘與伺服器時區差異影響。
export class SessionDeadline {
  constructor(now = () => performance.now()) { this.now = now; this.absolute = 0; this.idle = 0; }
  update(timing) {
    const at = this.now();
    this.absolute = at + Math.max(0, timing.expiresAt - timing.serverTime) * 1000;
    this.idle = Math.min(this.absolute, at + Math.max(0, timing.idleExpiresAt - timing.serverTime) * 1000);
  }
  state() {
    const absolute = Math.max(0, this.absolute - this.now()), idle = Math.max(0, this.idle - this.now());
    return {absolute, idle, mode:Math.min(absolute,idle) <= 0 ? 'expired' : absolute <= 120000 ? 'reauth' : idle <= 120000 ? 'warn' : 'active'};
  }
}
