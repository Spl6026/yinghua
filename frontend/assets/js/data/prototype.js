// [E-12] 原始交付的唯讀展示資料；正式內容 API 於後續階段實作。
export const places = [
    {id:1,name:'新竹市影像博物館',category:'景點',area:'北區',type:'文化場館',desc:'從舊城戲院走進影像的時光，認識新竹人的共同記憶。',hours:'09:30–17:00',image:'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=80',address:'新竹市中正路65號',rating:'城市記憶',discount:''},
    {id:2,name:'東門市場',category:'美食',area:'東區',type:'老城美食',desc:'白天買菜、夜裡吃一碗。穿梭市場巷弄，遇見新竹的多種日常。',hours:'依店家公告',image:'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=900&q=80',address:'新竹市東區大同路86號',rating:'巷弄探索',discount:''},
    {id:3,name:'或者工藝櫥窗',category:'店家',area:'北區',type:'老屋・選物',desc:'在街角老屋裡，看見工藝與地方生活交會的樣子。',hours:'11:00–18:00',image:'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=80',address:'新竹市北區中央路',rating:'在地好店',discount:'出示城市散策頁面，贈限定小禮（示意）'},
    {id:4,name:'新竹公園・麗池',category:'景點',area:'東區',type:'公園・散步',desc:'沿著池畔慢慢走，讓午後的光落在城市的綠意裡。',hours:'全天開放',image:'https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=900&q=80',address:'新竹市東區公園路',rating:'散步提案',discount:''},
    {id:5,name:'或者咖啡',category:'美食',area:'北區',type:'咖啡・甜點',desc:'找個靠窗的位置，為散步中場留一點安靜。',hours:'10:00–18:00',image:'https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&w=900&q=80',address:'新竹市北區大同路',rating:'風城咖啡',discount:''},
    {id:6,name:'山房選物所',category:'店家',area:'東區',type:'選物・生活',desc:'從生活器物開始，收藏一點竹塹的手感與溫度。',hours:'12:00–19:00',image:'https://images.unsplash.com/photo-1490312278390-ab64016e0aa9?auto=format&fit=crop&w=900&q=80',address:'新竹市東區民生路',rating:'本月優惠',discount:'消費滿額享 9 折（示意）'}
  ];
export const films = [
    {date:'10.12',day:'MON',name:'島嶼的慢鏡頭',genre:'紀錄片・映後座談',time:'14:00',desc:'在潮聲與風之間，找一種靠近土地的方式。',image:'https://images.unsplash.com/photo-1518837695005-2083093ee35b?auto=format&fit=crop&w=1000&q=80'},
    {date:'10.14',day:'WED',name:'窗邊的星期天',genre:'劇情長片',time:'19:00',desc:'一扇窗，看見三個人的日常。',image:'https://images.unsplash.com/photo-1519608487953-e999c86e7455?auto=format&fit=crop&w=1000&q=80'},
    {date:'10.16',day:'FRI',name:'風從城裡來',genre:'台灣經典修復',time:'18:30',desc:'一封寄不出去的信，與一座記得人的城。',image:'https://images.unsplash.com/photo-1470252649378-9c29740c9fa8?auto=format&fit=crop&w=1000&q=80'},
    {date:'10.16',day:'FRI',name:'光的形狀',genre:'短片選集',time:'15:00',desc:'四位創作者，四種觀看世界的方式。',image:'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=1000&q=80'}
  ];
export const news = [
    {date:'10.10',type:'活動',title:'風城慢慢市集｜老城散步日',desc:'市集回到舊城巷弄，手作、老屋與熟悉的風，陪你過一個慢一點的週末。'},
    {date:'10.11',type:'活動',title:'在城市裡收集光｜街拍小展',desc:'影像博物館二樓展出，免費參觀。展期至 10 月 30 日。'},
    {date:'10.18',type:'活動',title:'風的聲音｜午後草地音樂會',desc:'在新竹公園草地聽一場秋日下午的現場演出。'},
    {date:'10.20',type:'城市消息',title:'舊城街區步行環境改善公告',desc:'部分路段施工期間請依現場指示通行，散步路線可改走中央路。'}
  ];
export const offers = [
    {id:101,title:'或者工藝櫥窗｜散步小禮',merchant:'或者工藝櫥窗',area:'北區',discount:'出示映畫竹塹頁面，贈限定小禮',startDate:'2026-10-01',endDate:'2026-12-31',desc:'在街角老屋裡，看見工藝與地方生活交會的樣子。散步到店出示本頁，即可領取店家準備的小禮。',image:places[2].image,status:'已發布',terms:'每人限領一份，數量有限，送完為止。'},
    {id:102,title:'山房選物所｜生活選物 9 折',merchant:'山房選物所',area:'東區',discount:'消費滿額享 9 折',startDate:'2026-10-01',endDate:'2026-11-30',desc:'從生活器物開始，收藏一點竹塹的手感與溫度。單筆消費滿額即可享優惠。',image:places[5].image,status:'已發布',terms:'優惠品項與消費門檻依店家現場公告。'},
    {id:103,title:'秋日咖啡散步地圖限定',merchant:'或者咖啡',area:'北區',discount:'出示活動頁面享季節飲品小驚喜',startDate:'2026-10-10',endDate:'2026-11-15',desc:'沿著舊城散步地圖走進咖啡香，點一杯季節限定飲品，讓旅行多一段慢下來的時間。',image:places[4].image,status:'已發布',terms:'每人每次消費限兌換一次。'}
  ];
export const routes = [{id:'old-town',title:'一日光影・老城散步',theme:'一日・影像與舊城',desc:'影像博物館 → 東門市場 → 新竹公園',places:[1,2,4]},{id:'park-coffee',title:'公園樹影與一杯手沖',theme:'午後・公園與咖啡',desc:'新竹公園 → 或者咖啡 → 山房選物所',places:[4,5,6]}];
export const itineraryPreview = [
  {id:1,time:'10:00',name:'新竹市影像博物館',area:'北區・文化場館',desc:'看一場電影，認識新竹的影像記憶。'},
  {id:2,time:'12:00',name:'東門市場',area:'東區・老城美食',desc:'在市場巷弄裡，找一碗喜歡的滋味。'},
  {id:4,time:'15:00',name:'新竹公園・麗池',area:'東區・散步提案',desc:'午後沿著池畔慢慢走。'},
];
export const detailLinks = {"新竹市影像博物館": "/museum.html", "東門市場": "/places/dongmen-market.html", "新竹公園・麗池": "/places/hsinchu-park.html", "或者工藝櫥窗": "/places/or-arts.html", "或者咖啡": "/places/or-coffee.html", "山房選物所": "/places/shanfang.html", "島嶼的慢鏡頭": "/films/island-slow.html", "光的形狀": "/films/shape-of-light.html", "風從城裡來": "/films/wind-city.html", "窗邊的星期天": "/films/window-sunday.html", "秋日咖啡散步地圖上線": "/news/coffee-walk.html", "舊城街區步行環境改善公告": "/news/old-town-notice.html", "風的聲音｜午後草地音樂會": "/news/park-concert.html", "風城慢慢市集｜老城散步日": "/news/slow-market.html", "在城市裡收集光｜街拍小展": "/news/street-photo.html", "秋日咖啡散步地圖限定": "/offers/autumn-coffee.html?id=103", "或者工藝櫥窗｜散步小禮": "/offers/or-crafts.html?id=101", "山房選物所｜生活選物 9 折": "/offers/shanfang.html?id=102"};
