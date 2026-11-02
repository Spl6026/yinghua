// 只等待使用者已明確觸發的寫入完成；不做離線重送，也不保存私人資料。
export class PendingWrites {
  constructor() {
    this.operations = new Set();
  }
  get active() {
    return this.operations.size > 0;
  }
  run(action) {
    const operation = Promise.resolve().then(action);
    this.operations.add(operation);
    const done = () => this.operations.delete(operation);
    void operation.then(done, done);
    return operation;
  }
  async confirmed() {
    const results = await Promise.allSettled([...this.operations]);
    return results.every(result => result.status === 'fulfilled' && result.value === true);
  }
}
