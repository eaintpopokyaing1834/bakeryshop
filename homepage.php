<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Heaven Bakery Shop</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-white">

    <!-- Navbar -->
    <nav class="bg-white border-b border-stone-100 px-4">
        <article class="container mx-auto py-4 p-6 flex gap-4 flex justify-between">
            <div class="flex gap-2">
                <h1 class="text-4xl font-bold text-stone-800">
                    Sweet Heaven
                </h1>
                <img src="images/shoplogo.png" class="w-18 h-12">
            </div>

            <ul class="hidden md:flex gap-6 text-slate-800 font-semibold text-lg items-center ">
                <li><a href="#">Home</a></li>
                <li><a href="#">Categories</a></li>
                <li><a href="#" class="border border-stone-200 text-stone-600 px-4 py-2 rounded-full text-sm hover:bg-stone-50">Sign up</a></li>
                <li><a href="#">Login</a></li>
                <li><a href="#">EN</a></li>
            </ul>


            <div class="flex gap-3 items-center">
                <!-- <img src="images/search.png" class="w-6 h-6"> -->
                <!-- <img src="images/profile.png" class="w-6 h-6"> -->
                <img src="images/cart.png" class="w-6 h-6">


                <button class="bg-rose-500 text-white px-5 py-2 rounded-full hover:bg-rose-600">
                    Order Now
                </button>
            </div>

    </nav>
    <article class="flex bg-white mt-6 items-center justify-center">
        <section class="max-w-xl  w-full flex justify-between  p-3">
            <input type="search" placeholder="🔍Search for products by flavor ..."
                class="w-full border border-stone-200 rounded-l-full px-5 py-3 focus:outline-none focus:ring-2 focus:ring-rose-300">
            <button class="bg-rose-500 px-8 rounded-r-full text-white font-semibold text-lg">Seach</button>
        </section>
    </article>

    <!-- Hero Section -->
    <section class="bg-white py-12">

        <div class="container mx-auto px-4">

            <div class="grid md:grid-cols-2 items-center gap-10">

                <div>

                    <h1 class="text-5xl font-bold text-stone-800 mb-6">
                        Fresh & Delicious Cakes
                    </h1>

                    <p class="text-gray-500 text-lg mb-8">
                        A Taste of Heaven in Every Bite!
                        Freshly baked,happily made.<br>
                        Baked with high quality ingredients and a lot of love.

                    </p>

                    <button class="bg-rose-500 text-white px-8 py-3 rounded-full hover:bg-rose-600">
                        View Products
                    </button>

                </div>

                <div class="grid grid-cols-2">
                    <img src="images/maincake.jpg" alt="" class="">
                    <img src="images/bread.jpg" alt="" class="">
                    <img src="images/lemon.jpg" alt="" class="">
                    <img src="images/donuts.jpg" alt="" class="w-50">



                </div>

            </div>

        </div>

    </section>

    <!-- Features -->
    <section class="py-16 bg-stone-50">

        <div class="container mx-auto px-6">

            <div class="grid grid-cols-3 gap-10 text-center">

                <div class="flex flex-col items-center">
                    <img src="images/freshbread.png" class="w-10 ">
                    <h3 class="font-bold text-stone-700 text-xl">
                        Fresh Daily
                    </h3>
                    <p class="text-gray-500">
                        Freshly baked every day
                    </p>
                </div>

                <div class="flex flex-col items-center">
                    <img src="images/ingredient.png" class="w-10">

                    <h3 class="font-bold text-stone-700 text-xl">
                        Natural Ingredients
                    </h3>
                    <p class="text-gray-500">
                        Healthy and delicious
                    </p>
                </div>

                <div class="flex flex-col items-center">
                    <img src="images/quality.png" class="w-10">
                    <h3 class="font-bold text-stone-700 text-xl">
                        Premium Quality
                    </h3>
                    <p class="text-gray-500">
                        Best ingredients only
                    </p>
                </div>

            </div>

        </div>

    </section>
    <!-- Categories -->
    <section class="py-30">
        <h1 class="text-4xl font-bold text-center text-stone-800 m-8">Our Categories</h1>

        <div class="container mx-auto px-6 grid grid-cols-4 gap-12">

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/ceremony.jpg" class="h-28 mx-auto">
                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Ceremony Cakes
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/slicecake.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Slice Cakes
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/cupcake1.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Cup Cakes
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/bread1.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Breads
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/pastry.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Pastries
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/donut4.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Donuts
                    </p>
                </div>
            </div>

            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/pizza2.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Savory Items
                    </p>
                </div>
            </div>



            <div class="flex flex-col text-center">
                <div class="bg-white p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                    <img src="images/pudd.jpg" class="h-28 mx-auto">

                    <p class="text-stone-700 font-bold text-xl mt-3">
                        Desserts
                    </p>
                </div>
            </div>
            <button
                class="mt-5 bg-rose-500 text-white px-6 py-2 flex items-center justify-center rounded-full hover:bg-rose-600">
                View all products
            </button>


        </div>

    </section>

    <!-- Product Cards -->
    <section class="py-20 ">

        <div class="container mx-auto px-6">

            <h2 class="text-4xl font-bold text-center text-stone-800 mb-12">
                Best Seller Products
            </h2>

            <div class="grid md:grid-cols-4 gap-10 ">

                <div class="flex flex-col text-center">
                    <div class="bg-rose-50 p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                        <img src="images/diana.jpg" class="h-30 mx-auto">

                        <p class="text-stone-700 font-bold text-xl mt-3">
                            Birthday Cake
                        </p>
                        <p class="text-slate-500 font-bold text-lg mt-3">
                            Starting from 20,000 MMK
                        </p>
                        <button class="mt-5 bg-rose-500 text-white px-6 py-2 rounded-full hover:bg-rose-600">
                            Buy Now
                        </button>
                    </div>
                </div>

                <div class="flex flex-col text-center">
                    <div class="bg-rose-50 p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                        <img src="images/do.jpg" class="h-30 mx-auto">

                        <p class="text-stone-700 font-bold text-xl mt-3">
                            Donut
                        </p>
                        <p class="text-slate-500 font-bold text-lg mt-3">
                            2000(1 pic)/13000(pack)
                        </p>
                        <button class="mt-5 bg-rose-500 text-white px-6 py-2 rounded-full hover:bg-rose-600">
                            Buy Now
                        </button>
                    </div>
                </div>

                <div class="flex flex-col text-center">
                    <div class="bg-rose-50 p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                        <img src="images/slice.jpg" class="h-30 mx-auto">

                        <p class="text-stone-700 font-bold text-xl mt-3">
                            Slice Cake
                        </p>
                        <p class="text-slate-500 font-bold text-lg mt-3">
                            3000 MMK to 8000 MMK
                        </p>
                        <button class="mt-5 bg-rose-500 text-white px-6 py-2 rounded-full hover:bg-rose-600">
                            Buy Now
                        </button>
                    </div>
                </div>

                <div class="flex flex-col text-center">
                    <div class="bg-rose-50 p-6 rounded-2xl shadow-sm text-center hover:shadow-md transition-all duration-300">
                        <img src="images/burger.png" class="h-30 mx-auto">

                        <p class="text-stone-700 font-bold text-xl mt-3">
                            Burger
                        </p>
                        <p class="text-slate-500 font-bold text-lg mt-3">
                            5000 MMK to 10000 MMK
                        </p>
                        <button class="mt-5 bg-rose-500 text-white px-6 py-2 rounded-full hover:bg-rose-600">
                            Buy Now
                        </button>
                    </div>
                </div>

            </div>



        </div>

        </div>

    </section>

    <!-- About -->
    <section class="bg-stone-50 py-24">

        <div class="container mx-auto px-6">

            <div class="grid md:grid-cols-2 gap-10 items-center">

                <div>
                    <img src="images/big-cake.png" class="w-full">
                </div>

                <div>

                    <h2 class="text-4xl font-bold text-stone-800 mb-6">
                        Secret of Our Cakes
                    </h2>

                    <p class="text-gray-600 leading-8">
                        We use fresh fruits, premium cream,
                        natural ingredients and handmade recipes
                        to create delicious cakes.
                    </p>

                    <button class="mt-8 bg-rose-500 text-white px-6 py-3 rounded-full">
                        Learn More
                    </button>

                </div>

            </div>

        </div>

    </section>

    <!-- Footer -->
    <footer class="bg-stone-800 text-white py-8">

        <div class="container mx-auto text-center">

            <h2 class="text-2xl font-bold">
                Sweet Heaven Bakery
            </h2>

            <p class="mt-3">
                © 2026 All Rights Reserved
            </p>

        </div>

    </footer>

</body>

</html>