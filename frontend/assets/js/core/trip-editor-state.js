// [S E15/G15；E-33] 草稿與已保存版本分開；所有權改變會清除私人草稿。
export class TripEditorState {
  constructor(api, readIdentity, newId = () => crypto.randomUUID()) {
    this.api = api;
    this.readIdentity = readIdentity;
    this.newId = newId;
    this.generation = 0;
    this.busy = false;
    this.loading = false;
    this.page = 1;
    this.total = 0;
    this.summaries = [];
    this.reset();
  }
  reset() {
    ++this.generation;
    this.member = null;
    this.trip = null;
    this.draft = null;
    this.loaded = false;
    this.dirty = false;
    this.stale = false;
    this.uncertain = false;
    this.currentId = null;
    this.pendingCreateId = null;
    this.summaries = [];
    this.total = 0;
  }
  async perform(action, writing = false) {
    if (this.busy || this.loading) throw new Error('請等待目前操作完成。');
    const generation = ++this.generation,
      previous = this.member?.id;
    this.busy = writing;
    this.loading = !writing;
    try {
      const member = await this.readIdentity();
      if (!member) throw Object.assign(new Error('請先登入會員；既有未儲存內容保留。'), { status: 401 });
      if (previous && previous !== member.id) {
        this.reset();
        throw Object.assign(new Error('登入身分已改變，私人草稿已清除，請重新載入。'), { status: 409 });
      }
      this.member = member;
      const result = await action();
      const after = await this.readIdentity();
      if (!after) throw Object.assign(new Error('登入已到期，請重新驗證後再確認行程。'), { status: 401 });
      if (generation !== this.generation || after?.id !== member.id) {
        this.reset();
        throw Object.assign(new Error('登入身分已改變，請重新載入行程。'), { status: 409 });
      }
      return result;
    } catch (error) {
      if (writing && this.draft) {
        if (error.status === 409) this.stale = true;
        if (error.status === 0 || error instanceof TypeError) this.uncertain = true;
      }
      throw error;
    } finally {
      this.busy = false;
      this.loading = false;
    }
  }
  adopt(trip) {
    this.trip = trip;
    this.draft = {
      title: trip.title,
      tripDate: trip.tripDate || '',
      items: trip.items.map(item => ({ ...item, startTime: item.startTime || '' })),
    };
    this.loaded = true;
    this.dirty = false;
    this.stale = false;
    this.uncertain = false;
    if (trip.isCurrent) this.currentId = trip.id;
  }
  async list(page = this.page) {
    const data = await this.perform(() => this.api.list(page));
    this.summaries = data.items;
    this.total = data.total;
    this.page = data.page;
    this.currentId = data.currentId;
    return data;
  }
  async load() {
    const data = await this.list();
    if (data.currentId) {
      const result = await this.perform(() => this.api.get(data.currentId));
      this.adopt(result.itinerary);
    } else {
      this.trip = null;
      this.draft = null;
      this.loaded = true;
      this.dirty = false;
      this.stale = false;
      this.uncertain = false;
    }
  }
  async afterWrite(trip) {
    this.adopt(trip);
    try {
      await this.list(1);
    } catch (error) {
      error.saved = true;
      throw error;
    }
  }
  async create() {
    this.pendingCreateId ||= this.newId();
    const result = await this.perform(() => this.api.create({ id: this.pendingCreateId }), true);
    this.pendingCreateId = null;
    await this.afterWrite(result.itinerary);
  }
  async open(id) {
    const read = await this.perform(() => this.api.get(id));
    const result = await this.perform(() => this.api.select(id, read.itinerary.version), true);
    await this.afterWrite(result.itinerary);
  }
  async save() {
    if (!this.trip || !this.draft || this.stale || this.uncertain)
      throw new Error('請先重新載入行程，再確認修改。');
    const body = {
      title: this.draft.title,
      tripDate: this.draft.tripDate || null,
      version: this.trip.version,
      items: this.draft.items.map(item => ({
        attractionId: item.attractionId,
        startTime: item.startTime || null,
        note: item.note || '',
      })),
    };
    const result = await this.perform(() => this.api.save(this.trip.id, body), true);
    await this.afterWrite(result.itinerary);
  }
  async removeTrip() {
    if (!this.trip) throw new Error('請先開啟行程。');
    await this.perform(() => this.api.delete(this.trip.id, this.trip.version), true);
    this.trip = null;
    this.draft = null;
    this.dirty = false;
    try {
      await this.load();
    } catch (error) {
      error.saved = true;
      throw error;
    }
  }
  async apply(routeId) {
    if (!this.trip) throw new Error('請先新增或開啟行程。');
    const result = await this.perform(() => this.api.apply(this.trip.id, routeId, this.trip.version), true);
    await this.afterWrite(result.itinerary);
  }
  add(place) {
    if (!this.draft) throw new Error('請先新增或開啟行程。');
    if (this.draft.items.some(item => item.attractionId === place.id)) return false;
    this.draft.items.push({
      attractionId: place.id,
      startTime: '',
      note: '',
      snapshotName: place.name,
      attraction: place,
      availability: 'available',
    });
    this.dirty = true;
    return true;
  }
  move(index, delta) {
    const items = this.draft?.items,
      other = index + delta;
    if (!items || index < 0 || index >= items.length || other < 0 || other >= items.length) return false;
    [items[index], items[other]] = [items[other], items[index]];
    this.dirty = true;
    return true;
  }
  remove(index) {
    if (!this.draft || index < 0 || index >= this.draft.items.length) return false;
    this.draft.items.splice(index, 1);
    this.dirty = true;
    return true;
  }
}
