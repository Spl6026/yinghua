// 原型中的英文裝飾文案保留原樣，只補上讓螢幕閱讀器正確發音的語言標示。
// 僅標示已核對的介面文案；不推測管理員內容、片名或其他外語的語言。
const englishInterfaceText = new Set([
  'CONTENT',
  'SYSTEM',
  'SCROLL TO DISCOVER ↓',
  '01 / THE MUSEUM',
  '02 / ON THIS WEEK',
  '03 / TASTE THE CITY',
  '04 / AROUND TOWN',
  'A CITY, A STORY, A FRAME',
  'A WEEK AT THE IMAGE MUSEUM',
  'ADMIN / ACCOUNT',
  'CITY NOTEBOOK',
  'CITY NOTEBOOK / ARCHIVE',
  'CITY NOTEBOOK / NEWS',
  'CITY NOTEBOOK / UPDATE',
  'CITY ROUTE COLLECTION',
  'CITY STORY / DETAIL',
  'CONTENT / DASHBOARD',
  'CONTENT / MEMBERS',
  'CONTENT / NEWS',
  'CONTENT / OFFERS',
  'CONTENT / ROUTES',
  'CONTENT MANAGEMENT',
  'CONTENT MANAGEMENT / FILMS',
  'CONTENT MANAGEMENT / PLACES',
  'CURATED CITY GUIDE',
  'MEMBER TERMS',
  'PRIVACY POLICY',
  'PAGE NOT FOUND · 404',
  'EDITOR’S ROUTE',
  'EDITOR’S ROUTES',
  'EXPLORE MORE OF HSINCHU',
  'HAPPENING IN HSINCHU',
  'HSINCHU IMAGE MUSEUM / SCREENING',
  'HSINCHU LOCAL GUIDE',
  'Hsinchu Image Museum',
  'Hsinchu · City in Frame',
  'IMAGE MUSEUM / SCREENING',
  'JOIN THE CITY STORY',
  'LOCAL OFFERS / HSINCHU',
  'LOCAL PICKS',
  'MAKE IT YOURS',
  'MY FAVORITES',
  'MY HSINCHU',
  'SEARCH THE CITY',
  'SLOW DOWN, LOOK AROUND',
  'SYSTEM / ACCESS CONTROL',
  'SYSTEM / ADMINISTRATORS',
  'VISIT THE MUSEUM',
  'WELCOME BACK',
  'YOUR CITY COLLECTION',
  'YOUR NEXT TRIP',
  'YOUR OWN CITY STORY',
  'YOUR TRIP',
]);

export function annotateEnglishPassages(html) {
  // 程式、JSON、CSS 與輸入值不是介面段落，不改寫其內容。
  const chunks = html.split(
    /(<(?:script|style|textarea|pre|code|title)\b[\s\S]*?<\/(?:script|style|textarea|pre|code|title)>)/gi
  );
  return chunks
    .map((chunk, index) =>
      index % 2
        ? chunk
        : chunk.replace(
            /<(p|span|small|strong|div|h[1-6])\b([^>]*)>([^<]*)<\/\1>/g,
            (element, tag, attributes, text) => {
              if (
                /\blang\s*=/i.test(attributes) ||
                !englishInterfaceText.has(text.trim().replace(/\s+/g, ' '))
              )
                return element;
              return `<${tag}${attributes} lang="en">${text}</${tag}>`;
            }
          )
    )
    .join('');
}
