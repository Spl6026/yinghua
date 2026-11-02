// 目前行程只管理伺服器上已保存的版本，不在 localStorage 另存一份。
export class CurrentTripState {
  constructor(api, readIdentity) {
    this.api = api;
    this.readIdentity = readIdentity;
    this.generation = 0;
    this.busy = false;
    this.loading = false;
    this.reset();
  }
  reset() {
    ++this.generation;
    this.member = undefined;
    this.itinerary = null;
    this.items = [];
    this.nextCursor = null;
    this.loaded = false;
  }
  async load(more = false) {
    if (this.busy || (more && !this.nextCursor)) return false;
    const request = ++this.generation;
    const cursor = more ? this.nextCursor : '';
    const before = this.itinerary;
    this.loading = true;
    try {
      const member = await this.readIdentity();
      if (!member) throw Object.assign(new Error('請先登入會員。'), { status: 401 });
      const data = await this.api.itinerary(cursor);
      const currentMember = await this.readIdentity();
      if (request !== this.generation) return false;
      if (currentMember?.id !== member.id)
        throw Object.assign(new Error('登入身分已變更，請重新載入。'), { status: 409 });
      if (more && (data.itinerary?.id !== before?.id || data.itinerary?.version !== before?.version))
        throw Object.assign(new Error('行程已變更，請重新載入。'), { status: 409 });
      this.member = member;
      this.itinerary = data.itinerary;
      this.items = more ? [...this.items, ...(data.itinerary?.items || [])] : data.itinerary?.items || [];
      this.nextCursor = data.nextCursor;
      this.loaded = true;
      return true;
    } catch (error) {
      if (request !== this.generation) return false;
      this.member = undefined;
      this.itinerary = null;
      this.items = [];
      this.nextCursor = null;
      this.loaded = false;
      throw error;
    } finally {
      if (request === this.generation) this.loading = false;
    }
  }
  async change(id, remove = false) {
    if (this.busy || this.loading || !this.loaded) return { busy: true };
    const memberId = this.member.id;
    this.busy = true;
    const request = ++this.generation;
    try {
      const result = await this.api[remove ? 'removeStop' : 'addStop'](id, this.itinerary?.version || 0);
      const member = await this.readIdentity();
      if (request !== this.generation || member?.id !== memberId) {
        this.reset();
        throw Object.assign(new Error('登入狀態已變更，請重新載入行程。'), { status: 409 });
      }
      if (this.itinerary) this.itinerary = { ...this.itinerary, version: result.version };
      this.nextCursor = null;
      return result;
    } catch (error) {
      // 409 代表版本衝突，必須重新讀取；網路逾時則不能假設伺服器沒有保存。
      if (error.status === 409 || error.status === 0 || error.status === 401) this.reset();
      throw error;
    } finally {
      this.busy = false;
    }
  }
}
