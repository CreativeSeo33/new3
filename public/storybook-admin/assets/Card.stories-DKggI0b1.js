import{d as i,a as s,g as d,j as u,r as o,m as p,u as f,o as r}from"./vue.esm-bundler-RVqp0tw1.js";import{c as v}from"./cn-2dOUpm6k.js";import{_}from"./Button.vue_vue_type_script_setup_true_lang-DN4VgcsU.js";const h={key:0,class:"flex items-center p-4 border-b"},B={class:"p-4"},$={key:1,class:"flex items-center p-4 border-t"},n=i({__name:"Card",setup(a){return(e,g)=>(r(),s("div",p({class:f(v)("rounded-lg border bg-card text-card-foreground shadow-sm",e.$attrs.class)},{...e.$attrs,class:void 0}),[e.$slots.header?(r(),s("div",h,[o(e.$slots,"header")])):d("",!0),u("div",B,[o(e.$slots,"default")]),e.$slots.footer?(r(),s("div",$,[o(e.$slots,"footer")])):d("",!0)],16))}}),w={title:"UI/Card",component:n,render:a=>({components:{Card:n,Button:_},setup:()=>({args:a}),template:`
      <Card>
        <template #header>
          <div class="flex items-center justify-between w-full">
            <div class="font-medium">Заголовок</div>
            <Button size="sm" variant="outline">Действие</Button>
          </div>
        </template>
        <div class="text-sm text-muted-foreground">
          Контент карточки
        </div>
        <template #footer>
          <div class="ml-auto">
            <Button size="sm">Ок</Button>
          </div>
        </template>
      </Card>
    `})},t={};var m,l,c;t.parameters={...t.parameters,docs:{...(m=t.parameters)==null?void 0:m.docs,source:{originalSource:"{}",...(c=(l=t.parameters)==null?void 0:l.docs)==null?void 0:c.source}}};const y=["Default"];export{t as Default,y as __namedExportsOrder,w as default};
