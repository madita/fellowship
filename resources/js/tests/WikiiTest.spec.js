import { shallowMount } from '@vue/test-utils';
import Wiki from '../pages/landing/Wiki.vue';
import Create from '../components/wiki/Create.vue';
import Edit from '../components/wiki/Edit.vue';
import Category from '../components/wiki/Category.vue';

//test to create wiki
//tests not working because of buble error


describe('Wiki', () => {
    it('works', () => {
       shallowMount(Wiki);
    });
});

//test create
describe('Create', () => {
    it('works', () => {
       shallowMount(Create);
    });
});

//test edit
// describe('Edit', () => {
//     it('works', () => {
//        shallowMount(Edit);
//     });
//
//     it('test edit', () => {
//         const wrapper = shallowMount(Edit);
//         const input = wrapper.find('input');
//         input.setValue('test');
//         expect(input.element.value).toBe('test');
//     })
// });
//
// //test category
// describe('Category', () => {
//     it('works', () => {
//         shallowMount(Category);
//     });
//
//     it('test category', () => {
//         const wrapper = shallowMount(Category);
//         const input = wrapper.find('input');
//         input.setValue('test');
//         expect(input.element.value).toBe('test');
//     });
//
// });

