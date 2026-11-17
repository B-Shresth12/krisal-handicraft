<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\MoneyFields;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Basics')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, string $operation): void {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $get('name')));
                                        }
                                    })
                                    ->columnSpanFull(),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                TextInput::make('public_id')
                                    ->label('Public ID')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Stable identifier used by the storefront.'),
                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Textarea::make('short_description')
                                    ->rows(2)
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->rows(6)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Pricing & Stock')
                            ->columns(2)
                            ->schema([
                                ...MoneyFields::make('price', 'Price', required: true),
                                ...MoneyFields::make('compare_at_price', 'Compare-at price', compareTo: 'price'),
                                TextInput::make('stock_quantity')
                                    ->label('Stock quantity')
                                    ->numeric()
                                    ->integer()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('The in stock, low stock and out of stock status is derived from this.'),
                                TextInput::make('rating')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(5)
                                    ->step(0.1)
                                    ->default(0),
                                TextInput::make('review_count')
                                    ->label('Review count')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                            ]),

                        Tab::make('Media & Options')
                            ->schema([
                                Section::make('Images')
                                    ->schema([
                                        Repeater::make('images')
                                            ->relationship()
                                            ->orderColumn('sort_order')
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add image')
                                            ->itemLabel(fn (array $state): ?string => $state['alt_text'] ?? null)
                                            ->helperText('The first image is the primary one shown in listings.')
                                            ->schema([
                                                FileUpload::make('path')
                                                    ->label('Image')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('catalog')
                                                    ->maxSize(8192)
                                                    ->imageResizeTargetWidth(1600)
                                                    ->imageResizeMode('contain')
                                                    ->imageResizeUpscale(false)
                                                    ->required()
                                                    ->columnSpanFull(),
                                                TextInput::make('alt_text')
                                                    ->label('Alt text')
                                                    ->maxLength(255),
                                            ]),
                                    ]),
                                Section::make('Colours')
                                    ->schema([
                                        Repeater::make('colors')
                                            ->relationship()
                                            ->orderColumn('sort_order')
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add colour')
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255),
                                                ColorPicker::make('hex')
                                                    ->label('Colour')
                                                    ->format('hex')
                                                    ->required(),
                                            ]),
                                    ]),
                                Section::make('Sizes')
                                    ->schema([
                                        Repeater::make('sizes')
                                            ->relationship()
                                            ->orderColumn('sort_order')
                                            ->defaultItems(0)
                                            ->addActionLabel('Add size')
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->schema([
                                                TextInput::make('label')
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),
                                    ]),
                                Section::make('Specifications')
                                    ->schema([
                                        Repeater::make('specifications')
                                            ->relationship()
                                            ->orderColumn('sort_order')
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->addActionLabel('Add specification')
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->schema([
                                                TextInput::make('label')
                                                    ->required()
                                                    ->maxLength(255),
                                                Textarea::make('value')
                                                    ->rows(2)
                                                    ->maxLength(255)
                                                    ->required()
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Merchandising & Collections')
                            ->columns(3)
                            ->schema([
                                Toggle::make('is_bestseller')
                                    ->label('Bestseller'),
                                Toggle::make('is_new_arrival')
                                    ->label('New arrival'),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Select::make('collections')
                                    ->relationship('collections', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Etsy Reference')
                            ->columns(2)
                            ->schema([
                                TextInput::make('etsy_listing_id')
                                    ->label('Etsy listing ID')
                                    ->maxLength(255),
                                TextInput::make('etsy_url')
                                    ->label('Etsy URL')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }
}
