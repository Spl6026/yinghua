import {listOffers} from '../api/offers-api.js';
import {offerCards} from '../core/offer-view.js';
import {initializePublicList} from '../core/public-list-controller.js';
import {fallbackContentImages} from '../core/content-view.js';

initializePublicList({stateSelector:'#public-offers-state',listSelector:'#offers-list',loadData:listOffers,renderItems:offerCards,
  titleFor:data=>`店家優惠${data.page>1?'・第 '+data.page+' 頁':''}｜映畫竹塹`,typeFromUrl:()=>''});
fallbackContentImages();
